#!/usr/bin/env python3
"""BosskuAI continuation helper.

Keeps cross-tool handoff honest: another model can continue only from saved
state, not from hidden chat context.
"""
from __future__ import annotations

import argparse
import datetime as dt
import json
from pathlib import Path

ACTIVE = "ai-assistant/memory/active-continuation.md"
RUNS = "ai-assistant/runs"
STATE = "ai-assistant/runtime/system_state.json"


def root(raw: str | None) -> Path:
    return Path(raw).expanduser().resolve() if raw else Path(__file__).resolve().parents[2]


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def read_json(path: Path, default: dict) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default


def write_json(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False, sort_keys=True) + "\n", encoding="utf-8")


def latest_run(r: Path) -> dict:
    files = sorted((r / RUNS).glob("run_*.json"), reverse=True)
    if not files:
        return {}
    return read_json(files[0], {})


def show(r: Path) -> int:
    p = r / ACTIVE
    if p.exists():
        print(p.read_text(encoding="utf-8"))
    else:
        print("No active continuation file found.")
    return 0


def claim(r: Path, tool: str, note: str) -> int:
    run = latest_run(r)
    rid = run.get("id", "unknown")
    task = run.get("task", "unknown")
    path = r / ACTIVE
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(f"""# Active Continuation

Status: claimed
Run ID: {rid}
Claimed by: {tool}
Updated: {now()}
Task: {task}

## Current Note

{note or 'Continue from saved run packet, memory query, and repo state.'}

## Required Completion

Before stopping, run:

```bash
scripts/bosskuai runs complete {rid} --tool {tool} --summary "<outcome, verification, risks, next action>"
```
""", encoding="utf-8")
    state_path = r / STATE
    state = read_json(state_path, {"schema": "bosskuai.system_state.v1"})
    state["continuation"] = {"status": "claimed", "run_id": rid, "tool": tool, "file": ACTIVE}
    state["updated_at"] = now()
    write_json(state_path, state)
    print(f"Claimed continuation for {rid} as {tool}")
    return 0


def clear(r: Path) -> int:
    path = r / ACTIVE
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(f"# Active Continuation\n\nStatus: clear\nUpdated: {now()}\n\nNo shared handoff is active.\n", encoding="utf-8")
    print("Continuation cleared.")
    return 0


def main() -> int:
    ap = argparse.ArgumentParser(description="BosskuAI cross-tool continuation helper")
    ap.add_argument("--root", default=None)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("show")
    cl = sub.add_parser("claim"); cl.add_argument("--tool", default="manual"); cl.add_argument("--note", default="")
    sub.add_parser("clear")
    args = ap.parse_args()
    r = root(args.root)
    if args.cmd == "show":
        return show(r)
    if args.cmd == "claim":
        return claim(r, args.tool, args.note)
    if args.cmd == "clear":
        return clear(r)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
