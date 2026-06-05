#!/usr/bin/env python3
"""BosskuAI CLI command center without UI."""
from __future__ import annotations

import argparse
import json
import os
import sqlite3
import subprocess
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
STATE = "ai-assistant/runtime/system_state.json"
INBOX = "ai-assistant/memory/inbox.jsonl"
DURABLE = "ai-assistant/memory/durable-memory.md"
RUNS = "ai-assistant/runs"


def root(raw: str | None = None) -> Path:
    return Path(raw).expanduser().resolve() if raw else ROOT


def run_py(r: Path, rel: str, args: list[str], *, capture: bool = False, timeout: int | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(r / rel), *args],
        cwd=str(r),
        input="",
        text=True,
        capture_output=capture,
        timeout=timeout,
        check=False,
    )


def read_json(path: Path, default: dict[str, Any]) -> dict[str, Any]:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default


def write_json(path: Path, data: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False, sort_keys=True) + "\n", encoding="utf-8")


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    rows: list[dict[str, Any]] = []
    for line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        if line.strip():
            try:
                rows.append(json.loads(line))
            except Exception:
                pass
    return rows


def write_jsonl(path: Path, rows: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("".join(json.dumps(r, ensure_ascii=False, sort_keys=True) + "\n" for r in rows), encoding="utf-8")


def memory_counts(r: Path) -> dict[str, Any]:
    inbox = read_jsonl(r / INBOX)
    pending = sum(1 for row in inbox if row.get("status") == "pending")
    approved = sum(1 for row in inbox if row.get("status") == "approved")
    rejected = sum(1 for row in inbox if row.get("status") == "rejected")
    db = r / "ai-assistant/memory/semantic-memory.sqlite3"
    chunks = 0
    if db.exists():
        try:
            conn = sqlite3.connect(str(db))
            chunks = conn.execute("select count(*) from chunks").fetchone()[0]
            conn.close()
        except Exception:
            chunks = 0
    return {"pending_inbox": pending, "approved_inbox": approved, "rejected_inbox": rejected, "vector_chunks": chunks, "db_exists": db.exists()}


def cmd_status(args: argparse.Namespace) -> int:
    r = root(args.root)
    state = read_json(r / STATE, {})
    counts = memory_counts(r)
    print("BosskuAI Status")
    print(f"Project      : {state.get('project', 'unknown')}")
    print(f"Tool         : {state.get('active_tool', 'unknown')}")
    print(f"Last run     : {state.get('last_run_id', '-')}")
    cont = state.get("continuation") or {}
    print(f"Continuation : {cont.get('status', 'unknown')} {cont.get('run_id', '')}")
    print(f"Memory DB    : {'present' if counts['db_exists'] else 'missing'}")
    print(f"Vector chunks: {counts['vector_chunks']}")
    print(f"Inbox pending: {counts['pending_inbox']} approved={counts['approved_inbox']} rejected={counts['rejected_inbox']}")
    models = state.get("models", {})
    print(f"Models       : plan={models.get('planner','frontier')} execute={models.get('executor','lower-cost')} audit={models.get('auditor','frontier')}")
    risk = state.get("risk") or {}
    if risk:
        print(f"Last risk    : {risk.get('level')} {risk.get('reasons', [])}")
    return 0


def cmd_run(args: argparse.Namespace) -> int:
    r = root(args.root)
    res = run_py(r, "ai-assistant/scripts/orchestrator.py", ["create", "--root", str(r), "--tool", args.tool, *(["--no-memory"] if args.no_memory else []), args.task], timeout=30)
    return res.returncode


def cmd_runs_list(args: argparse.Namespace) -> int:
    r = root(args.root)
    files = sorted((r / RUNS).glob("run_*.json"), reverse=True)[: args.limit]
    for f in files:
        row = read_json(f, {})
        print(f"{row.get('id')} | {row.get('status')} | risk={row.get('routing',{}).get('risk_level')} | {row.get('task','')[:90]}")
    return 0


def cmd_runs_show(args: argparse.Namespace) -> int:
    r = root(args.root)
    path = r / RUNS / f"{args.run_id}.json"
    if not path.exists():
        print(f"Run not found: {args.run_id}", file=sys.stderr)
        return 1
    print(path.read_text(encoding="utf-8"))
    return 0


def cmd_runs_complete(args: argparse.Namespace) -> int:
    r = root(args.root)
    cmd = ["complete", args.run_id, "--root", str(r), "--tool", args.tool, "--summary", args.summary]
    if args.audit:
        cmd += ["--audit", args.audit]
    if args.no_sync:
        cmd += ["--no-sync"]
    if args.keep_continuation:
        cmd += ["--keep-continuation"]
    res = run_py(r, "ai-assistant/scripts/orchestrator.py", cmd, timeout=45)
    return res.returncode


def cmd_memory_inbox(args: argparse.Namespace) -> int:
    rows = read_jsonl(root(args.root) / INBOX)
    pending = [row for row in rows if row.get("status") == args.status]
    for idx, row in enumerate(pending, 1):
        print(f"[{idx}] {row.get('id')} | {row.get('type')} | confidence={row.get('confidence')} | {row.get('content')}")
    if not pending:
        print(f"No {args.status} memory candidates.")
    return 0


def select_pending(rows: list[dict[str, Any]], selector: str) -> tuple[int, dict[str, Any]] | None:
    pending_indexes = [i for i, row in enumerate(rows) if row.get("status") == "pending"]
    if selector.isdigit():
        n = int(selector) - 1
        if 0 <= n < len(pending_indexes):
            i = pending_indexes[n]
            return i, rows[i]
    for i, row in enumerate(rows):
        if row.get("id") == selector:
            return i, row
    return None


def append_durable(r: Path, row: dict[str, Any]) -> None:
    p = r / DURABLE
    p.parent.mkdir(parents=True, exist_ok=True)
    with p.open("a", encoding="utf-8") as f:
        f.write(f"\n## Memory Inbox Approved — {row.get('id')}\n\n")
        f.write(f"- **Type:** {row.get('type')}\n")
        f.write(f"- **Source:** {row.get('source')} / {row.get('source_event')}\n")
        f.write(f"- **Confidence:** {row.get('confidence')}\n\n")
        f.write("```text\n" + str(row.get("content", "")).strip() + "\n```\n")


def cmd_memory_approve(args: argparse.Namespace) -> int:
    r = root(args.root)
    path = r / INBOX
    rows = read_jsonl(path)
    selected = select_pending(rows, args.selector)
    if not selected:
        print(f"Pending memory not found: {args.selector}", file=sys.stderr)
        return 1
    i, row = selected
    rows[i]["status"] = "approved"
    append_durable(r, rows[i])
    write_jsonl(path, rows)
    if args.sync:
        run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "sync"], timeout=30)
    print(f"Approved memory: {row.get('id')}")
    return 0


def cmd_memory_reject(args: argparse.Namespace) -> int:
    r = root(args.root)
    path = r / INBOX
    rows = read_jsonl(path)
    selected = select_pending(rows, args.selector)
    if not selected:
        print(f"Pending memory not found: {args.selector}", file=sys.stderr)
        return 1
    i, row = selected
    rows[i]["status"] = "rejected"
    write_jsonl(path, rows)
    print(f"Rejected memory: {row.get('id')}")
    return 0


def cmd_memory_search(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "query", args.query, "--limit", str(args.limit)]).returncode


def cmd_memory_extract(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/memory_extractor.py", ["--root", str(r)]).returncode


def cmd_memory_remember(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "remember", args.text, "--tool", args.tool, "--kind", args.kind, "--source", args.source]).returncode


def cmd_memory_sync(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "sync"], timeout=60).returncode


def cmd_memory_autopromote(args: argparse.Namespace) -> int:
    r = root(args.root)
    path = r / INBOX
    rows = read_jsonl(path)
    count = 0
    for row in rows:
        if row.get("status") == "pending" and float(row.get("confidence") or 0) >= args.min_confidence and row.get("type") in {"decision", "preference", "constraint"}:
            row["status"] = "approved"
            row["autopromoted"] = True
            append_durable(r, row)
            count += 1
    write_jsonl(path, rows)
    if count and args.sync:
        run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "sync"], timeout=60)
    print(f"Autopromoted {count} high-confidence memory candidate(s).")
    return 0


def cmd_memory_doctor(args: argparse.Namespace) -> int:
    r = root(args.root)
    counts = memory_counts(r)
    files = [
        "ai-assistant/memory/conversation-log.jsonl",
        "ai-assistant/memory/session-log.jsonl",
        "ai-assistant/memory/durable-memory.md",
        "ai-assistant/memory/active-continuation.md",
        "ai-assistant/memory/vector-config.json",
    ]
    print("BosskuAI Memory Doctor")
    for rel in files:
        p = r / rel
        print(f"- {rel}: {'present' if p.exists() else 'missing'}")
    print(f"- semantic-memory.sqlite3: {'present' if counts['db_exists'] else 'missing'} chunks={counts['vector_chunks']}")
    print(f"- inbox: pending={counts['pending_inbox']} approved={counts['approved_inbox']} rejected={counts['rejected_inbox']}")
    return 0


def cmd_model_route(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/model_router.py", [args.task]).returncode


def cmd_route_detailed(args: argparse.Namespace) -> int:
    r = root(args.root)
    task = " ".join(args.task).strip()
    return run_py(r, "ai-assistant/scripts/model_router.py", ["--detailed", "--lines", task]).returncode


def cmd_eval_latest(args: argparse.Namespace) -> int:
    r = root(args.root)
    scripts = [
        "scripts/eval_workspace.py",
        "scripts/eval_expert_coverage.py",
        "scripts/eval_adversarial_routing.py",
        "scripts/eval_routing_generalization.py",
    ]
    status = 0
    for rel in scripts:
        if not (r / rel).exists():
            print(f"{rel} not found", file=sys.stderr)
            status = 1
            continue
        print(f"\n== {rel} ==")
        code = run_py(r, rel, [], timeout=60).returncode
        status = status or code
    return status


def cmd_eval_token(args: argparse.Namespace) -> int:
    r = root(args.root)
    cmd = ["--root", str(r), "--run-id", args.run_id]
    if args.score:
        cmd.append("--score")
    else:
        cmd.append("--emit-prompts")
    if args.json:
        cmd.append("--json")
    return run_py(r, "scripts/eval_token_budget.py", cmd, timeout=60).returncode


def cmd_continuation(args: argparse.Namespace) -> int:
    r = root(args.root)
    cmd = ["--root", str(r), args.cont_cmd]
    if args.cont_cmd == "claim":
        cmd += ["--tool", args.tool, "--note", args.note]
    return run_py(r, "ai-assistant/scripts/continuation.py", cmd).returncode


def cmd_session_start(args: argparse.Namespace) -> int:
    r = root(args.root)
    return run_py(r, "ai-assistant/scripts/auto_memory.py", ["--root", str(r), "session-start", "--tool", args.tool, "--text", args.text]).returncode


def cmd_session_end(args: argparse.Namespace) -> int:
    r = root(args.root)
    cmd = ["--root", str(r), "session-end", "--tool", args.tool, "--summary", args.summary]
    if args.no_sync:
        cmd += ["--no-sync"]
    return run_py(r, "ai-assistant/scripts/auto_memory.py", cmd, timeout=45).returncode


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="bosskuai", description="BosskuAI CLI command center")
    p.add_argument("--root", default=os.environ.get("BOSSKUAI_PROJECT_DIR"))
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("status"); s.set_defaults(fn=cmd_status)

    r = sub.add_parser("run"); r.add_argument("task"); r.add_argument("--tool", default="manual"); r.add_argument("--no-memory", action="store_true"); r.set_defaults(fn=cmd_run)

    runs = sub.add_parser("runs"); runs_sub = runs.add_subparsers(dest="runs_cmd", required=True)
    rl = runs_sub.add_parser("list"); rl.add_argument("--limit", type=int, default=10); rl.set_defaults(fn=cmd_runs_list)
    rs = runs_sub.add_parser("show"); rs.add_argument("run_id"); rs.set_defaults(fn=cmd_runs_show)
    rc = runs_sub.add_parser("complete"); rc.add_argument("run_id"); rc.add_argument("--tool", default="manual"); rc.add_argument("--summary", required=True); rc.add_argument("--audit", default=""); rc.add_argument("--no-sync", action="store_true"); rc.add_argument("--keep-continuation", action="store_true"); rc.set_defaults(fn=cmd_runs_complete)

    mem = sub.add_parser("memory"); mem_sub = mem.add_subparsers(dest="memory_cmd", required=True)
    mi = mem_sub.add_parser("inbox"); mi.add_argument("--status", default="pending"); mi.set_defaults(fn=cmd_memory_inbox)
    ma = mem_sub.add_parser("approve"); ma.add_argument("selector"); ma.add_argument("--no-sync", dest="sync", action="store_false", default=True); ma.set_defaults(fn=cmd_memory_approve)
    mr = mem_sub.add_parser("reject"); mr.add_argument("selector"); mr.set_defaults(fn=cmd_memory_reject)
    ms = mem_sub.add_parser("search"); ms.add_argument("query"); ms.add_argument("--limit", type=int, default=5); ms.set_defaults(fn=cmd_memory_search)
    me = mem_sub.add_parser("extract"); me.set_defaults(fn=cmd_memory_extract)
    mm = mem_sub.add_parser("remember"); mm.add_argument("text"); mm.add_argument("--tool", default="manual"); mm.add_argument("--kind", default="durable", choices=["conversation", "durable", "plan", "learning", "bug", "market", "continuation"]); mm.add_argument("--source", default="cli-remember"); mm.set_defaults(fn=cmd_memory_remember)
    mapr = mem_sub.add_parser("autopromote"); mapr.add_argument("--min-confidence", type=float, default=0.86); mapr.add_argument("--no-sync", dest="sync", action="store_false", default=True); mapr.set_defaults(fn=cmd_memory_autopromote)
    md = mem_sub.add_parser("doctor"); md.set_defaults(fn=cmd_memory_doctor)
    msy = mem_sub.add_parser("sync"); msy.set_defaults(fn=cmd_memory_sync)

    model = sub.add_parser("model"); model_sub = model.add_subparsers(dest="model_cmd", required=True)
    route = model_sub.add_parser("route"); route.add_argument("task"); route.set_defaults(fn=cmd_model_route)

    rt = sub.add_parser("route"); rt.add_argument("task", nargs="+", help="Prompt to classify"); rt.set_defaults(fn=cmd_route_detailed)

    cont = sub.add_parser("continuation"); cont_sub = cont.add_subparsers(dest="cont_cmd", required=True)
    cshow = cont_sub.add_parser("show"); cshow.set_defaults(fn=cmd_continuation)
    cclaim = cont_sub.add_parser("claim"); cclaim.add_argument("--tool", default="manual"); cclaim.add_argument("--note", default=""); cclaim.set_defaults(fn=cmd_continuation)
    cclear = cont_sub.add_parser("clear"); cclear.set_defaults(fn=cmd_continuation)

    sess = sub.add_parser("session"); sess_sub = sess.add_subparsers(dest="session_cmd", required=True)
    ss = sess_sub.add_parser("start"); ss.add_argument("--tool", default="manual"); ss.add_argument("--text", default=""); ss.set_defaults(fn=cmd_session_start)
    se = sess_sub.add_parser("end"); se.add_argument("--tool", default="manual"); se.add_argument("--summary", default=""); se.add_argument("--no-sync", action="store_true"); se.set_defaults(fn=cmd_session_end)

    ev = sub.add_parser("eval"); ev_sub = ev.add_subparsers(dest="eval_cmd", required=True)
    el = ev_sub.add_parser("latest"); el.set_defaults(fn=cmd_eval_latest)
    et = ev_sub.add_parser("token"); et.add_argument("--run-id", default="manual-token-run"); et.add_argument("--score", action="store_true"); et.add_argument("--json", action="store_true"); et.set_defaults(fn=cmd_eval_token)
    return p


def main() -> int:
    args = build_parser().parse_args()
    return args.fn(args)


if __name__ == "__main__":
    raise SystemExit(main())
