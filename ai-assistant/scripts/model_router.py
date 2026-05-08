#!/usr/bin/env python3
"""BosskuAI model routing: legacy role map + detailed deterministic route (CLI/workspace)."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import sys
from pathlib import Path

try:
    from risk_detector import detect
except Exception:  # pragma: no cover
    from ai_assistant.scripts.risk_detector import detect  # type: ignore

MODEL_STACK = {
    "router": os.environ.get("BOSSKU_ROUTER_MODEL", "gpt-5.5-instant"),
    "orchestrator": os.environ.get("BOSSKU_ORCHESTRATOR_MODEL", "gpt-5.5"),
    "executor_default": os.environ.get("BOSSKU_EXECUTOR_DEFAULT_MODEL", "kimi-k2.6"),
    "executor_high_risk": os.environ.get("BOSSKU_EXECUTOR_HIGH_RISK_MODEL", "gpt-5.5"),
    "auditor": os.environ.get("BOSSKU_AUDITOR_MODEL", "claude-opus-4.7"),
    "security_auditor": os.environ.get("BOSSKU_SECURITY_AUDITOR_MODEL", "claude-opus-4.7"),
    "final_reviewer": os.environ.get("BOSSKU_FINAL_REVIEWER_MODEL", "gpt-5.5"),
    "writer": os.environ.get("BOSSKU_WRITER_MODEL", "gpt-5.5"),
    "direct_answer": os.environ.get("BOSSKU_DIRECT_ANSWER_MODEL", "gpt-5.5-instant"),
}

FALLBACKS = {
    "router": MODEL_STACK["router"],
    "orchestrator": "claude-opus-4.7, glm-5.1, deepseek-v4-pro",
    "executor_normal": "deepseek-v4-pro, glm-5.1, gpt-5.5",
    "executor_high": "kimi-k2.6, deepseek-v4-pro, claude-opus-4.7",
}


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


def route_detailed(task: str) -> dict:
    """Mirror PHP heuristic routing (no LLM)."""
    t = task.strip()
    low = t.lower()
    risk = detect(t)

    task_type = "unknown"
    skill = "generic"
    workflow = "orchestrator_executor_auditor"
    needs_repo = True
    needs_file_edit = True
    needs_test_run = False
    needs_executor = True
    needs_auditor = True
    needs_security_auditor = False
    needs_final_reviewer = False
    executor_profile = "default"
    memory_mode = "read_and_write"
    estimated_token_level = "medium"
    reason = "Heuristic classification (Python)"

    if low.startswith(("explain ", "what is ", "what are ", "how does ", "why ", "define ")) or (
        "policy" in low and "gate" in low and " vs" in low
    ):
        task_type, skill, workflow = "question", "laravel", "direct_answer"
        needs_executor = needs_auditor = False
        needs_file_edit = False
        memory_mode = "none"
        estimated_token_level = "low"

    if any(x in low for x in ("social media", "festivent", "vendor signup")):
        task_type, skill = "marketing", "marketing"
        workflow = "writer_only"
        needs_repo = needs_file_edit = needs_executor = needs_auditor = False
        memory_mode = "read_only"
        estimated_token_level = "low"

    if "readme" in low or ("documentation" in low and "update" in low):
        task_type, skill = "documentation", "documentation"
        workflow = "writer_only"
        needs_file_edit = "update" in low or "edit" in low
        needs_executor = needs_file_edit
        needs_auditor = needs_file_edit
        memory_mode = "read_only"

    if "payment" in low or ("webhook" in low and "signature" in low):
        task_type, skill = "payment", "security"
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
        executor_profile = "high_risk"
        needs_security_auditor = needs_final_reviewer = True
        estimated_token_level = "very_high"

    if "authentication" in low or ("middleware" in low and "auth" in low):
        task_type, skill = "authentication", "security"
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
        executor_profile = "high_risk"
        needs_security_auditor = needs_final_reviewer = True

    if workflow != "direct_answer" and "policy" in low and ("laravel" in low or "gate" in low):
        task_type, skill = "authorization", "laravel"
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
        executor_profile = "high_risk"
        needs_security_auditor = needs_final_reviewer = True

    if "button" in low or "spacing" in low or ("dashboard" in low and "mobile" in low):
        task_type, skill = "ui_ux", "uiux"
        executor_profile = "frontend_ui"

    if "redis" in low:
        skill, executor_profile = "redis", "backend"

    if "validation message" in low and "typo" in low:
        task_type, skill = "bug_fix", "laravel"
        executor_profile = "backend"
        workflow = "orchestrator_executor_auditor"
        needs_final_reviewer = needs_security_auditor = False

    if "migration" in low and "subscription" in low:
        task_type, skill = "database", "laravel"
        executor_profile = "high_risk"
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
        needs_security_auditor = needs_final_reviewer = True

    if "deploy" in low and ("docker" in low or "nginx" in low or "ssl" in low):
        task_type, skill = "deployment", "devops"
        executor_profile = "devops"
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
        needs_security_auditor = needs_final_reviewer = True

    risk_level = risk.level
    sens_types = {"payment", "authentication", "authorization", "database", "deployment", "security"}

    if risk_level == "high":
        if executor_profile != "devops":
            executor_profile = "high_risk"
        needs_security_auditor = True
        needs_final_reviewer = True
        workflow = "orchestrator_executor_auditor_security_final_reviewer"
    else:
        needs_final_reviewer = False
        if needs_security_auditor or (task_type in sens_types):
            needs_security_auditor = task_type in sens_types or needs_security_auditor
        if workflow in ("direct_answer", "writer_only", "orchestrator_only"):
            pass
        elif needs_security_auditor:
            workflow = "orchestrator_executor_auditor_security"
        else:
            workflow = "orchestrator_executor_auditor"

    executor_model = (
        MODEL_STACK["executor_high_risk"] if executor_profile in ("high_risk",) else MODEL_STACK["executor_default"]
    )
    if executor_profile == "devops":
        executor_model = os.environ.get("BOSSKU_EXECUTOR_DEVOPS_MODEL", "gpt-5.5")

    run_id = hashlib.sha256(t.encode()).hexdigest()[:12]

    return {
        "schema": "bosskuai.model_route.v2",
        "run_id_hint": run_id,
        "task_type": task_type,
        "risk_level": risk_level,
        "skill": skill,
        "workflow": workflow,
        "needs_repo_context": needs_repo,
        "needs_file_edit": needs_file_edit,
        "needs_test_run": needs_test_run,
        "needs_executor": needs_executor,
        "needs_auditor": needs_auditor,
        "needs_security_auditor": needs_security_auditor,
        "needs_final_reviewer": needs_final_reviewer,
        "executor_profile": executor_profile,
        "memory_mode": memory_mode,
        "estimated_token_level": estimated_token_level,
        "reason": reason,
        "risk_detector": {"reasons": risk.reasons, "frontier_required": risk.frontier_required},
        "models": {
            "router": MODEL_STACK["router"],
            "router_fallback": "gpt-5.4, deepseek-v4-flash, glm-5.1",
            "orchestrator": MODEL_STACK["orchestrator"],
            "orchestrator_fallback": FALLBACKS["orchestrator"],
            "executor": executor_model,
            "executor_fallback": FALLBACKS["executor_high"] if executor_profile == "high_risk" else FALLBACKS["executor_normal"],
            "auditor": MODEL_STACK["auditor"],
            "security_auditor": MODEL_STACK["security_auditor"],
            "final_reviewer": MODEL_STACK["final_reviewer"],
            "writer": MODEL_STACK["writer"],
            "direct_answer": MODEL_STACK["direct_answer"],
        },
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Route a task through BosskuAI models.")
    parser.add_argument("task", nargs="*", help="Task text. If omitted, stdin is used.")
    parser.add_argument("--root", default=None)
    parser.add_argument("--detailed", action="store_true", help="Emit v2 workflow + model stack (deterministic)")
    parser.add_argument("--lines", action="store_true", help="With --detailed, also print human-readable summary lines")
    args = parser.parse_args()
    task = " ".join(args.task).strip() or sys.stdin.read().strip()
    if args.detailed:
        data = route_detailed(task)
        if args.lines:
            m = data["models"]
            print(f"Skill: {data['skill']}")
            print(f"Task Type: {data['task_type']}")
            print(f"Risk: {data['risk_level']}")
            print(f"Workflow: {data['workflow']}")
            print(f"Executor Profile: {data['executor_profile']}")
            print(f"Executor Model: {m['executor']}")
            print(f"Orchestrator: {m['orchestrator']}")
            print(f"Auditor: {m['auditor']}")
            print(f"Security Auditor: {m['security_auditor'] if data['needs_security_auditor'] else '(skipped)'}")
            print(f"Final Reviewer: {m['final_reviewer'] if data['needs_final_reviewer'] else '(skipped)'}")
            print(f"Memory mode: {data['memory_mode']}")
            print(f"Reason: {data['reason']}")
            print("--- JSON ---")
        print(json.dumps(data, indent=2, sort_keys=True))
    else:
        print(json.dumps(route(task), indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
