#!/usr/bin/env python3
"""
BosskuAI auto memory capture.

Local-first bridge between Claude Code, Cursor, Codex, markdown memory files,
and the sqlite vector memory index.

What it does:
- captures prompts / notes / plans / learnings into durable repo files
- writes an audit trail to conversation-log.jsonl
- deduplicates repeated hook events
- re-syncs semantic-memory.sqlite3 via vector_memory.py

No hosted service. No external dependency.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
import re
import select
import subprocess
import sys
from pathlib import Path
from typing import Any

MAX_CAPTURE_CHARS_DEFAULT = 4000
STATE_FILE = "ai-assistant/memory/auto-memory-state.json"
RAW_LOG_FILE = "ai-assistant/memory/conversation-log.jsonl"
CONVERSATION_MEMORY_FILE = "ai-assistant/memory/conversation-memory.md"
DURABLE_MEMORY_FILE = "ai-assistant/memory/durable-memory.md"
PLAN_LOG_FILE = "ai-assistant/memory/plan-log.md"
LEARNING_LOG_FILE = "ai-assistant/memory/learning-log.md"
ACTIVE_CONTINUATION_FILE = "ai-assistant/memory/active-continuation.md"
VECTOR_SCRIPT = "ai-assistant/scripts/vector_memory.py"

TEXT_KEYS = {
    "prompt",
    "user_prompt",
    "userPrompt",
    "message",
    "content",
    "text",
    "input",
    "query",
    "task",
    "transcript",
    "summary",
}

SENSITIVE_PATTERNS = [
    re.compile(r"(?i)(api[_-]?key|secret|token|password|passwd|pwd|client[_-]?secret|access[_-]?token|refresh[_-]?token)\s*[:=]\s*['\"]?[^\s'\"]+"),
    re.compile(r"(?i)bearer\s+[A-Za-z0-9._~+/=-]{12,}"),
    re.compile(r"(?i)(sk-[A-Za-z0-9]{20,}|ghp_[A-Za-z0-9_]{20,}|xox[baprs]-[A-Za-z0-9-]{20,})"),
    re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----", re.S),
]

SESSION_LOG_FILE = "ai-assistant/memory/session-log.jsonl"


def repo_root(default: str | None = None) -> Path:
    if default:
        return Path(default).expanduser().resolve()
    return Path(__file__).resolve().parents[2]


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def today() -> str:
    return dt.datetime.now(dt.timezone.utc).date().isoformat()


def ensure_memory_files(root: Path) -> None:
    memory_dir = root / "ai-assistant/memory"
    memory_dir.mkdir(parents=True, exist_ok=True)
    defaults = {
        CONVERSATION_MEMORY_FILE: "# Conversation Memory\n\nCross-tool captured conversation notes. This is indexed into vector memory.\n\n",
        DURABLE_MEMORY_FILE: "# Durable Memory\n\nExplicit decisions, stable preferences, constraints, and reusable context.\n\n",
        RAW_LOG_FILE: "",
        SESSION_LOG_FILE: "",
        STATE_FILE: json.dumps({"seen_hashes": []}, indent=2) + "\n",
    }
    for rel, content in defaults.items():
        p = root / rel
        if not p.exists():
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(content, encoding="utf-8")


def read_stdin() -> str:
    """Return currently available stdin without blocking wrapper commands.

    Some AI tool hooks run memory capture from a subprocess while stdin is still
    connected to the parent process. Blocking on sys.stdin.read() can freeze the
    orchestrator. We only read when data is immediately available.
    """
    if sys.stdin is None or sys.stdin.isatty():
        return ""
    try:
        ready, _, _ = select.select([sys.stdin], [], [], 0)
        if not ready:
            return ""
        return sys.stdin.read()
    except Exception:
        try:
            return sys.stdin.read()
        except Exception:
            return ""


def redact(text: str) -> str:
    redacted = text
    for pattern in SENSITIVE_PATTERNS:
        redacted = pattern.sub(lambda m: m.group(0).split("=")[0] + "=[REDACTED]" if "=" in m.group(0) else "[REDACTED_SECRET]", redacted)
    return redacted


def clean_text(text: str, max_chars: int) -> str:
    text = redact(text or "")
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = re.sub(r"\n{4,}", "\n\n\n", text).strip()
    if len(text) > max_chars:
        text = text[: max_chars - 80].rstrip() + "\n\n[truncated by BosskuAI auto_memory.py]"
    return text


def parse_json_maybe(raw: str) -> Any | None:
    raw = raw.strip()
    if not raw:
        return None
    try:
        return json.loads(raw)
    except Exception:
        return None


def collect_text_fields(obj: Any, found: list[str] | None = None, depth: int = 0) -> list[str]:
    if found is None:
        found = []
    if depth > 8:
        return found
    if isinstance(obj, dict):
        for key, value in obj.items():
            if key in TEXT_KEYS and isinstance(value, str):
                found.append(value)
            else:
                collect_text_fields(value, found, depth + 1)
    elif isinstance(obj, list):
        for item in obj:
            collect_text_fields(item, found, depth + 1)
    elif isinstance(obj, str) and depth == 0:
        found.append(obj)
    return found


def extract_text(raw: str, explicit_text: str | None, max_chars: int) -> str:
    if explicit_text:
        return clean_text(explicit_text, max_chars)
    parsed = parse_json_maybe(raw)
    if parsed is not None:
        texts = [t.strip() for t in collect_text_fields(parsed) if t and t.strip()]
        if texts:
            # Prefer the longest meaningful text field instead of dumping hook metadata.
            return clean_text(max(texts, key=len), max_chars)
        return clean_text(json.dumps(parsed, ensure_ascii=False), max_chars)
    return clean_text(raw, max_chars)


def sha(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def load_state(root: Path) -> dict[str, Any]:
    p = root / STATE_FILE
    if not p.exists():
        return {"seen_hashes": []}
    try:
        data = json.loads(p.read_text(encoding="utf-8"))
        if not isinstance(data.get("seen_hashes"), list):
            data["seen_hashes"] = []
        return data
    except Exception:
        return {"seen_hashes": []}


def save_state(root: Path, state: dict[str, Any]) -> None:
    p = root / STATE_FILE
    p.parent.mkdir(parents=True, exist_ok=True)
    # Keep state small; memory itself lives in markdown/jsonl files.
    state["seen_hashes"] = list(state.get("seen_hashes", []))[-500:]
    p.write_text(json.dumps(state, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def already_seen(root: Path, fingerprint: str) -> bool:
    state = load_state(root)
    seen = set(state.get("seen_hashes", []))
    if fingerprint in seen:
        return True
    state.setdefault("seen_hashes", []).append(fingerprint)
    save_state(root, state)
    return False


def append(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as f:
        f.write(content)


def append_raw_log(root: Path, payload: dict[str, Any]) -> None:
    append(root / RAW_LOG_FILE, json.dumps(payload, ensure_ascii=False, sort_keys=True) + "\n")


def append_session_log(root: Path, payload: dict[str, Any]) -> None:
    append(root / SESSION_LOG_FILE, json.dumps(payload, ensure_ascii=False, sort_keys=True) + "\n")


def session_event(root: Path, *, event: str, tool: str, source: str, text: str, sync: bool, quiet: bool) -> int:
    ensure_memory_files(root)
    payload = {
        "at": utc_now(),
        "event": event,
        "tool": tool,
        "source": source,
        "text": clean_text(text, MAX_CAPTURE_CHARS_DEFAULT),
    }
    append_session_log(root, payload)
    if event in {"session-end", "session-summary"} and payload["text"]:
        capture(root, event="outcome", tool=tool, source=source, text=payload["text"], kind="learning", sync=sync, quiet=quiet)
    elif event == "session-start" and payload["text"]:
        capture(root, event="continuation", tool=tool, source=source, text=payload["text"], kind="continuation", sync=False, quiet=quiet)
    if not quiet:
        print(f"BosskuAI session memory: {event} stored for tool={tool}", file=sys.stderr)
    return 0


def markdown_block(title: str, metadata: dict[str, str], text: str) -> str:
    lines = [f"## {title}", ""]
    for key, value in metadata.items():
        if value:
            lines.append(f"- **{key}:** {value}")
    lines.extend(["", "```text", text.strip(), "```", ""])
    return "\n".join(lines)


def classify_kind(event: str, text: str, explicit_kind: str | None) -> str:
    if explicit_kind:
        return explicit_kind
    lower = text.lower()
    if event in {"plan", "planned"} or any(k in lower for k in ["planned approach:", "execution plan:", "todo:"]):
        return "plan"
    if any(k in lower for k in ["bug:", "root cause:", "regression:", "fix:"]):
        return "bug"
    if any(k in lower for k in ["market:", "competitor:", "pricing:", "positioning:"]):
        return "market"
    if any(k in lower for k in ["decision:", "constraint:", "preference:", "remember:", "rule:"]):
        return "durable"
    if event in {"learning", "outcome", "assistant"}:
        return "learning"
    if event in {"handoff", "continuation"}:
        return "continuation"
    return "conversation"


def target_file_for(kind: str) -> str:
    return {
        "plan": PLAN_LOG_FILE,
        "learning": LEARNING_LOG_FILE,
        "bug": "ai-assistant/memory/bug-patterns.md",
        "market": "ai-assistant/memory/market-notes.md",
        "durable": DURABLE_MEMORY_FILE,
        "continuation": ACTIVE_CONTINUATION_FILE,
        "conversation": CONVERSATION_MEMORY_FILE,
    }.get(kind, CONVERSATION_MEMORY_FILE)


def capture(root: Path, *, event: str, tool: str, source: str, text: str, kind: str | None, sync: bool, quiet: bool) -> int:
    ensure_memory_files(root)
    if not text:
        if not quiet:
            print("BosskuAI auto memory: empty event skipped", file=sys.stderr)
        return 0

    memory_kind = classify_kind(event, text, kind)
    fingerprint = sha(json.dumps({"event": event, "tool": tool, "source": source, "text": text}, sort_keys=True))
    if already_seen(root, fingerprint):
        if not quiet:
            print("BosskuAI auto memory: duplicate event skipped", file=sys.stderr)
        return 0

    timestamp = utc_now()
    payload = {
        "at": timestamp,
        "event": event,
        "tool": tool,
        "source": source,
        "kind": memory_kind,
        "sha256": fingerprint,
        "text": text,
    }
    append_raw_log(root, payload)

    title = f"{today()} — {tool} — {event}"
    rel_target = target_file_for(memory_kind)
    block = markdown_block(
        title,
        {
            "Tool": tool,
            "Event": event,
            "Source": source,
            "Kind": memory_kind,
            "Captured at": timestamp,
        },
        text,
    )
    append(root / rel_target, block)

    if sync:
        sync_vector(root, quiet=quiet)
    if not quiet:
        print(f"BosskuAI auto memory: stored kind={memory_kind} target={rel_target}", file=sys.stderr)
    return 0


def sync_vector(root: Path, quiet: bool = False) -> int:
    script = root / VECTOR_SCRIPT
    if not script.exists():
        if not quiet:
            print(f"BosskuAI auto memory: missing {VECTOR_SCRIPT}; sync skipped", file=sys.stderr)
        return 0
    cmd = [sys.executable, str(script), "--root", str(root), "sync"]
    result = subprocess.run(cmd, cwd=str(root), text=True, capture_output=True)
    if result.returncode != 0:
        print(result.stderr or result.stdout, file=sys.stderr)
    elif not quiet:
        print(result.stdout, file=sys.stderr)
    return 0 if result.returncode == 0 else result.returncode


def query_vector(root: Path, query: str, limit: int) -> int:
    script = root / VECTOR_SCRIPT
    cmd = [sys.executable, str(script), "--root", str(root), "query", query, "--limit", str(limit)]
    return subprocess.run(cmd, cwd=str(root)).returncode


def status(root: Path) -> int:
    ensure_memory_files(root)
    log_path = root / RAW_LOG_FILE
    log_count = 0
    if log_path.exists():
        with log_path.open("r", encoding="utf-8") as f:
            log_count = sum(1 for _ in f)
    print(f"BosskuAI auto memory root: {root}")
    print(f"Raw events: {log_count} ({RAW_LOG_FILE})")
    for rel in [CONVERSATION_MEMORY_FILE, DURABLE_MEMORY_FILE, PLAN_LOG_FILE, LEARNING_LOG_FILE]:
        p = root / rel
        print(f"- {rel}: {'present' if p.exists() else 'missing'}")
    return query_vector(root, "auto memory status durable plan learning", 3) if (root / "ai-assistant/memory/semantic-memory.sqlite3").exists() else 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="BosskuAI auto memory capture and vector sync.")
    parser.add_argument("--root", default=os.environ.get("BOSSKUAI_PROJECT_DIR") or os.environ.get("CLAUDE_PROJECT_DIR"))
    sub = parser.add_subparsers(dest="command", required=True)

    cap = sub.add_parser("capture")
    cap.add_argument("--event", default="note")
    cap.add_argument("--tool", default=os.environ.get("BOSSKUAI_TOOL", "unknown"))
    cap.add_argument("--source", default="manual")
    cap.add_argument("--kind", choices=["conversation", "durable", "plan", "learning", "bug", "market", "continuation"])
    cap.add_argument("--text")
    cap.add_argument("--max-chars", type=int, default=MAX_CAPTURE_CHARS_DEFAULT)
    cap.add_argument("--no-sync", action="store_true")
    cap.add_argument("--quiet", action="store_true")
    cap.add_argument("--echo-stdin", action="store_true", help="Echo stdin back for hook chains.")

    rem = sub.add_parser("remember")
    rem.add_argument("text", nargs="?")
    rem.add_argument("--tool", default=os.environ.get("BOSSKUAI_TOOL", "manual"))
    rem.add_argument("--kind", choices=["conversation", "durable", "plan", "learning", "bug", "market", "continuation"], default="durable")
    rem.add_argument("--source", default="manual-remember")
    rem.add_argument("--no-sync", action="store_true")

    q = sub.add_parser("query")
    q.add_argument("text")
    q.add_argument("--limit", type=int, default=5)

    ss = sub.add_parser("session-start")
    ss.add_argument("--tool", default=os.environ.get("BOSSKUAI_TOOL", "unknown"))
    ss.add_argument("--source", default="session-start")
    ss.add_argument("--text", default="")
    ss.add_argument("--quiet", action="store_true")

    se = sub.add_parser("session-end")
    se.add_argument("--tool", default=os.environ.get("BOSSKUAI_TOOL", "unknown"))
    se.add_argument("--source", default="session-end")
    se.add_argument("--summary", default="")
    se.add_argument("--no-sync", action="store_true")
    se.add_argument("--quiet", action="store_true")

    sub.add_parser("sync")
    sub.add_parser("status")
    return parser


def main() -> int:
    args = build_parser().parse_args()
    root = repo_root(args.root)

    if args.command == "capture":
        raw = "" if args.text is not None else read_stdin()
        text = extract_text(raw, args.text, args.max_chars)
        code = capture(
            root,
            event=args.event,
            tool=args.tool,
            source=args.source,
            text=text,
            kind=args.kind,
            sync=not args.no_sync,
            quiet=args.quiet,
        )
        if args.echo_stdin and raw:
            sys.stdout.write(raw)
        return code

    if args.command == "remember":
        raw = "" if args.text else read_stdin()
        text = args.text or clean_text(raw, MAX_CAPTURE_CHARS_DEFAULT)
        return capture(
            root,
            event="remember",
            tool=args.tool,
            source=args.source,
            text=text,
            kind=args.kind,
            sync=not args.no_sync,
            quiet=False,
        )

    if args.command == "session-start":
        raw = "" if args.text else read_stdin()
        text = args.text or clean_text(raw, MAX_CAPTURE_CHARS_DEFAULT) or "Session started. Read active continuation and query vector memory before meaningful work."
        return session_event(root, event="session-start", tool=args.tool, source=args.source, text=text, sync=False, quiet=args.quiet)

    if args.command == "session-end":
        raw = "" if args.summary else read_stdin()
        text = args.summary or clean_text(raw, MAX_CAPTURE_CHARS_DEFAULT)
        # Empty summary is still recorded in session-log but is not promoted into durable learning.
        return session_event(root, event="session-end", tool=args.tool, source=args.source, text=text, sync=not args.no_sync, quiet=args.quiet)

    if args.command == "query":
        return query_vector(root, args.text, args.limit)
    if args.command == "sync":
        return sync_vector(root, quiet=False)
    if args.command == "status":
        return status(root)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
