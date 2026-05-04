#!/usr/bin/env python3
"""Extract candidate durable memories into memory/inbox.jsonl.

This is intentionally conservative and local-first. It does not call an LLM; it
uses deterministic rules so hooks can run safely and cheaply.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import re
import sys
from pathlib import Path

RAW_LOG = "ai-assistant/memory/conversation-log.jsonl"
INBOX = "ai-assistant/memory/inbox.jsonl"
STATE = "ai-assistant/memory/memory-extractor-state.json"

CANDIDATE_PATTERNS = [
    ("decision", re.compile(r"(?i)\b(decision|decided|we will use|use postgresql|use mysql|use maria ?db|architecture choice)\b")),
    ("preference", re.compile(r"(?i)\b(prefer|preference|from now on|going forward|always|never|avoid)\b")),
    ("constraint", re.compile(r"(?i)\b(constraint|must|must not|cannot|no need|without ui|no ui|no ai slob)\b")),
    ("technical", re.compile(r"(?i)\b(laravel|nuxt|docker|redis|postgresql|mariadb|cursor|codex|claude|vector db|frontier model)\b")),
]
SECRET_RE = re.compile(r"(?i)(api[_-]?key|secret|token|password|bearer|private key|\.env)")


def repo_root(raw: str | None) -> Path:
    return Path(raw).expanduser().resolve() if raw else Path(__file__).resolve().parents[2]


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def sha(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def load_json(path: Path, default: dict) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default


def append_jsonl(path: Path, row: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(row, ensure_ascii=False, sort_keys=True) + "\n")


def classify(text: str) -> tuple[str | None, float]:
    if SECRET_RE.search(text):
        return None, 0.0
    best: tuple[str | None, float] = (None, 0.0)
    for kind, pattern in CANDIDATE_PATTERNS:
        if pattern.search(text):
            score = {"decision": 0.9, "preference": 0.86, "constraint": 0.84, "technical": 0.68}[kind]
            if score > best[1]:
                best = (kind, score)
    return best


def compact(text: str, limit: int = 500) -> str:
    text = re.sub(r"\s+", " ", text).strip()
    return text if len(text) <= limit else text[: limit - 20].rstrip() + " ...[truncated]"


def extract(root: Path) -> int:
    raw_path = root / RAW_LOG
    inbox_path = root / INBOX
    state_path = root / STATE
    state = load_json(state_path, {"seen_source_hashes": [], "seen_content_hashes": []})
    seen_source = set(state.get("seen_source_hashes", []))
    seen_content = set(state.get("seen_content_hashes", []))
    created = 0
    if not raw_path.exists():
        print("No conversation-log.jsonl found.")
        return 0
    for line in raw_path.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        try:
            row = json.loads(line)
        except Exception:
            continue
        source_hash = row.get("sha256") or sha(line)
        if source_hash in seen_source:
            continue
        text = str(row.get("text") or "")
        kind, confidence = classify(text)
        seen_source.add(source_hash)
        if not kind:
            continue
        content = compact(text)
        content_hash = sha(f"{kind}:{content.lower()}")
        if content_hash in seen_content:
            continue
        seen_content.add(content_hash)
        append_jsonl(inbox_path, {
            "id": f"mem_{now().replace(':','').replace('-','')}_{created+1}",
            "at": now(),
            "content": content,
            "type": kind,
            "source": row.get("tool", "unknown"),
            "source_event": row.get("event", "unknown"),
            "source_hash": source_hash,
            "confidence": confidence,
            "status": "pending",
        })
        created += 1
    state_path.write_text(json.dumps({
        "seen_source_hashes": sorted(seen_source)[-2000:],
        "seen_content_hashes": sorted(seen_content)[-2000:],
        "updated_at": now(),
    }, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(f"Extracted {created} pending memory candidate(s).")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract BosskuAI memory candidates into inbox.")
    parser.add_argument("--root", default=None)
    args = parser.parse_args()
    return extract(repo_root(args.root))

if __name__ == "__main__":
    raise SystemExit(main())
