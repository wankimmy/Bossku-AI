#!/usr/bin/env python3
"""BosskuAI always-on model routing policy.

The script emits role decisions, not vendor-specific model names. Configure your tool
(Claude Code, Cursor, Codex, or wrapper) to map `frontier` and `lower-cost` to
actual models.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

try:
    from risk_detector import detect
except Exception:  # pragma: no cover
    from ai_assistant.scripts.risk_detector import detect  # type: ignore


def repo_root(default: str | None = None) -> Path:
    return Path(default).expanduser().resolve() if default else Path(__file__).resolve().parents[2]


def route(task: str) -> dict:
    risk = detect(task)
    executor = "frontier" if risk.frontier_required else "lower-cost"
    return {
        "schema": "bosskuai.model_route.v1",
        "risk_level": risk.level,
        "risk_reasons": risk.reasons,
        "phases": {
            "plan": {"model_role": "frontier", "required": True},
            "execute": {"model_role": executor, "required": True},
            "audit": {"model_role": "frontier", "required": True},
            "memory": {"model_role": "system", "required": True},
        },
        "policy": "plan=frontier, execute=lower-cost unless escalated, audit=frontier",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Route a task through BosskuAI model phases.")
    parser.add_argument("task", nargs="*", help="Task text. If omitted, stdin is used.")
    parser.add_argument("--root", default=None)
    args = parser.parse_args()
    task = " ".join(args.task).strip() or sys.stdin.read().strip()
    print(json.dumps(route(task), indent=2, sort_keys=True))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
