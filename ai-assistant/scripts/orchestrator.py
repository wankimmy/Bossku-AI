#!/usr/bin/env python3
"""BosskuAI Plan -> Execute -> Audit -> Memory run orchestrator.

This is a local protocol engine. It creates structured run artifacts and prompt
packets for Claude Code, Cursor, Codex, or manual usage. It does not pretend to
call hosted models by itself.

v1.9.5 hardening:
- subprocess memory calls never inherit stdin, so CLI runs cannot hang;
- every run writes a continuation packet immediately;
- every run writes plan + continuation memory and updates system state;
- run completion can save outcome/audit memory and clear live continuation.
"""
from __future__ import annotations

import argparse
import datetime as dt
import json
import subprocess
import sys
from pathlib import Path
from typing import Any

from model_router import route
import auto_memory

RUNS_DIR = "ai-assistant/runs"
STATE_FILE = "ai-assistant/runtime/system_state.json"
ACTIVE_CONTINUATION_FILE = "ai-assistant/memory/active-continuation.md"


def repo_root(raw: str | None = None) -> Path:
    return Path(raw).expanduser().resolve() if raw else Path(__file__).resolve().parents[2]


def now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def run_id() -> str:
    return "run_" + dt.datetime.now(dt.timezone.utc).strftime("%Y%m%d_%H%M%S_%f")


def write_json(path: Path, data: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False, sort_keys=True) + "\n", encoding="utf-8")


def read_json(path: Path, default: dict[str, Any]) -> dict[str, Any]:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return default


def update_state(root: Path, **updates: Any) -> None:
    state_path = root / STATE_FILE
    state = read_json(state_path, {"schema": "bosskuai.system_state.v1"})
    state.update({"updated_at": now(), **updates})
    write_json(state_path, state)


def call_memory(root: Path, args: list[str], timeout: int = 20) -> bool:
    """Call auto_memory safely inside the same Python process when possible.

    This avoids slow nested Python startup and avoids inherited-stdin hangs. Falls
    back to subprocess only for commands not handled here.
    """
    if args and args[0] == "capture":
        opts: dict[str, Any] = {
            "event": "note",
            "tool": "unknown",
            "source": "manual",
            "text": "",
            "kind": None,
            "sync": True,
            "quiet": False,
        }
        i = 1
        while i < len(args):
            item = args[i]
            if item == "--event" and i + 1 < len(args):
                opts["event"] = args[i + 1]; i += 2; continue
            if item == "--tool" and i + 1 < len(args):
                opts["tool"] = args[i + 1]; i += 2; continue
            if item == "--source" and i + 1 < len(args):
                opts["source"] = args[i + 1]; i += 2; continue
            if item == "--text" and i + 1 < len(args):
                opts["text"] = args[i + 1]; i += 2; continue
            if item == "--kind" and i + 1 < len(args):
                opts["kind"] = args[i + 1]; i += 2; continue
            if item == "--no-sync":
                opts["sync"] = False; i += 1; continue
            if item == "--quiet":
                opts["quiet"] = True; i += 1; continue
            i += 1
        try:
            code = auto_memory.capture(
                root,
                event=str(opts["event"]),
                tool=str(opts["tool"]),
                source=str(opts["source"]),
                text=str(opts["text"]),
                kind=opts["kind"],
                sync=bool(opts["sync"]),
                quiet=bool(opts["quiet"]),
            )
            return code == 0
        except Exception as exc:
            print(f"BosskuAI memory capture failed: {exc}", file=sys.stderr)
            return False

    script = root / "ai-assistant/scripts/auto_memory.py"
    if not script.exists():
        return False
    try:
        result = subprocess.run(
            [sys.executable, str(script), "--root", str(root), *args],
            cwd=str(root),
            input="",
            text=True,
            capture_output=True,
            timeout=timeout,
            check=False,
        )
        if result.returncode != 0 and result.stderr:
            print(result.stderr.strip(), file=sys.stderr)
        return result.returncode == 0
    except subprocess.TimeoutExpired:
        print("BosskuAI memory call timed out; run packet was still written.", file=sys.stderr)
        return False


def write_continuation(root: Path, packet: dict[str, Any], next_action: str) -> None:
    routing = packet.get("routing", {})
    phases = packet.get("phases", {})
    content = f"""# Active Continuation

Status: active
Run ID: {packet['id']}
Tool: {packet['tool']}
Task: {packet['task']}
Updated: {now()}
Risk: {routing.get('risk_level', 'unknown')} — {', '.join(routing.get('risk_reasons', [])) or 'none'}

## Model Flow

- Plan: {phases.get('plan', {}).get('model_role', 'frontier')}
- Execute: {phases.get('execute', {}).get('model_role', 'lower-cost')}
- Audit: {phases.get('audit', {}).get('model_role', 'frontier')}
- Memory: system

## Next Action

{next_action}

## Resume Commands

```bash
scripts/bosskuai runs show {packet['id']}
scripts/bosskuai continuation show
scripts/bosskuai runs complete {packet['id']} --tool <claude|cursor|codex> --summary "<what changed, verified, risks, next action>"
```

## Handoff Rule

Any Claude Code, Cursor, or Codex session may continue this task only from saved state: this file, the run JSON, memory query results, and repo diffs. Unsaved chat context from another tool is not assumed to exist.
"""
    path = root / ACTIVE_CONTINUATION_FILE
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def clear_continuation(root: Path, run_id_value: str) -> None:
    path = root / ACTIVE_CONTINUATION_FILE
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        f"# Active Continuation\n\nStatus: clear\nLast completed run: {run_id_value}\nUpdated: {now()}\n\nNo shared handoff is active.\n",
        encoding="utf-8",
    )


def run_path(root: Path, rid: str) -> Path:
    return root / RUNS_DIR / f"{rid}.json"


def create_run(root: Path, task: str, tool: str, no_memory: bool) -> dict[str, Any]:
    rid = run_id()
    routing = route(task)
    packet: dict[str, Any] = {
        "schema": "bosskuai.run.v1.9.5",
        "id": rid,
        "created_at": now(),
        "updated_at": now(),
        "tool": tool,
        "task": task,
        "routing": routing,
        "status": "planned",
        "memory_policy": {
            "save_plan": not no_memory,
            "save_continuation": not no_memory,
            "save_outcome_on_complete": not no_memory,
            "never_save_secrets": True,
        },
        "phases": {
            "plan": {
                "model_role": "frontier",
                "status": "ready",
                "instruction": "Create the plan, architecture, risk list, rollback path, and verification strategy before editing.",
            },
            "execute": {
                "model_role": routing["phases"]["execute"]["model_role"],
                "status": "pending",
                "instruction": "Execute only the saved plan. Escalate to frontier if risk increases or context is missing.",
            },
            "audit": {
                "model_role": "frontier",
                "status": "pending",
                "instruction": "Audit diff/output for correctness, security, tenant isolation, regression, token cost, and missed requirements.",
            },
            "memory": {
                "model_role": "system",
                "status": "pending",
                "instruction": "Save durable decision/outcome, update continuation state, extract candidate memories, and sync vector memory.",
            },
        },
        "prompts": {
            "planner": f"BosskuAI PLAN MODE. Use frontier model. Task: {task}",
            "executor": f"BosskuAI EXECUTE MODE. Use {routing['phases']['execute']['model_role']} model unless risk escalates. Follow saved plan and active continuation. Task: {task}",
            "auditor": f"BosskuAI AUDIT MODE. Use frontier model. Review plan, execution, tests, tenant/security risks, token budget, and memory save. Task: {task}",
        },
        "continuation_file": ACTIVE_CONTINUATION_FILE,
        "events": [],
    }

    packet["events"].append({"at": now(), "event": "run_created", "tool": tool})
    write_json(run_path(root, rid), packet)
    write_continuation(root, packet, "Plan with frontier model, then execute using the routed executor model.")
    update_state(
        root,
        last_run_id=rid,
        active_tool=tool,
        last_task=task[:240],
        models={
            "planner": routing["phases"]["plan"]["model_role"],
            "executor": routing["phases"]["execute"]["model_role"],
            "auditor": routing["phases"]["audit"]["model_role"],
        },
        risk={"level": routing["risk_level"], "reasons": routing["risk_reasons"]},
        continuation={"status": "active", "run_id": rid, "file": ACTIVE_CONTINUATION_FILE},
    )

    if not no_memory:
        plan_saved = call_memory(root, [
            "capture", "--event", "plan", "--tool", tool, "--kind", "plan", "--source", rid,
            "--text", packet["prompts"]["planner"], "--no-sync", "--quiet",
        ])
        continuation_saved = call_memory(root, [
            "capture", "--event", "continuation", "--tool", tool, "--kind", "continuation", "--source", rid,
            "--text", f"Run {rid} active. Task: {task}. Next: plan, execute, audit, memory save.", "--no-sync", "--quiet",
        ])
        packet["memory_saved"] = {"plan": plan_saved, "continuation": continuation_saved, "outcome": False}
        packet["phases"]["memory"]["status"] = "plan_saved" if plan_saved else "plan_save_failed"
        packet["events"].append({"at": now(), "event": "memory_plan_capture", "ok": plan_saved})
        write_json(run_path(root, rid), packet)
    else:
        packet["memory_saved"] = {"plan": False, "continuation": False, "outcome": False}
        write_json(run_path(root, rid), packet)

    return packet


def complete_run(root: Path, rid: str, tool: str, summary: str, audit: str, no_sync: bool, clear: bool) -> dict[str, Any]:
    path = run_path(root, rid)
    if not path.exists():
        raise FileNotFoundError(f"Run not found: {rid}")
    packet = read_json(path, {})
    packet["updated_at"] = now()
    packet["status"] = "completed"
    packet.setdefault("phases", {}).setdefault("execute", {})["status"] = "completed"
    packet.setdefault("phases", {}).setdefault("audit", {})["status"] = "completed" if audit else "not_provided"
    packet.setdefault("phases", {}).setdefault("memory", {})["status"] = "saving"
    packet["result"] = {"summary": summary, "audit": audit, "completed_by": tool, "completed_at": now()}
    packet.setdefault("events", []).append({"at": now(), "event": "run_completed", "tool": tool})
    write_json(path, packet)

    outcome_text = f"Run {rid} completed. Task: {packet.get('task')}\nOutcome: {summary}\nAudit: {audit or 'not provided'}"
    ok_learning = call_memory(root, [
        "capture", "--event", "outcome", "--tool", tool, "--kind", "learning", "--source", rid,
        "--text", outcome_text, "--quiet", *( ["--no-sync"] if no_sync else [] ),
    ])
    ok_durable = call_memory(root, [
        "capture", "--event", "decision", "--tool", tool, "--kind", "durable", "--source", rid,
        "--text", f"Completed run {rid}: {summary}", "--quiet", "--no-sync",
    ])
    packet["memory_saved"] = {**packet.get("memory_saved", {}), "outcome": ok_learning, "durable_summary": ok_durable}
    packet["phases"]["memory"]["status"] = "saved" if ok_learning else "save_failed"
    if clear:
        clear_continuation(root, rid)
        update_state(root, continuation={"status": "clear", "last_completed_run": rid, "file": ACTIVE_CONTINUATION_FILE})
    write_json(path, packet)
    return packet


def main() -> int:
    argv = sys.argv[1:]
    if argv and argv[0] == "create":
        parser = argparse.ArgumentParser(description="Create a BosskuAI structured run packet.")
        parser.add_argument("task", nargs="*", help="Task text. If omitted, stdin is used.")
        parser.add_argument("--root", default=None)
        parser.add_argument("--tool", default="manual")
        parser.add_argument("--no-memory", action="store_true")
        args = parser.parse_args(argv[1:])
        task = " ".join(args.task).strip() or sys.stdin.read().strip()
        if not task:
            print("Task is required.", file=sys.stderr)
            return 2
        packet = create_run(repo_root(args.root), task, args.tool, args.no_memory)
        print(json.dumps(packet, indent=2, ensure_ascii=False, sort_keys=True))
        return 0

    if argv and argv[0] == "complete":
        parser = argparse.ArgumentParser(description="Complete a BosskuAI structured run packet.")
        parser.add_argument("run_id")
        parser.add_argument("--root", default=None)
        parser.add_argument("--tool", default="manual")
        parser.add_argument("--summary", required=True)
        parser.add_argument("--audit", default="")
        parser.add_argument("--no-sync", action="store_true")
        parser.add_argument("--keep-continuation", action="store_true")
        args = parser.parse_args(argv[1:])
        try:
            packet = complete_run(repo_root(args.root), args.run_id, args.tool, args.summary, args.audit, args.no_sync, not args.keep_continuation)
        except Exception as exc:
            print(str(exc), file=sys.stderr)
            return 1
        print(json.dumps(packet, indent=2, ensure_ascii=False, sort_keys=True))
        return 0

    # Backward compatible: `orchestrator.py --root <root> --tool claude "task"`.
    parser = argparse.ArgumentParser(description="Create a BosskuAI structured run packet.")
    parser.add_argument("task", nargs="*", help="Task text. If omitted, stdin is used.")
    parser.add_argument("--root", default=None)
    parser.add_argument("--tool", default="manual")
    parser.add_argument("--no-memory", action="store_true")
    args = parser.parse_args(argv)
    task = " ".join(args.task).strip() or sys.stdin.read().strip()
    if not task:
        print("Task is required.", file=sys.stderr)
        return 2
    packet = create_run(repo_root(args.root), task, args.tool, args.no_memory)
    print(json.dumps(packet, indent=2, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
