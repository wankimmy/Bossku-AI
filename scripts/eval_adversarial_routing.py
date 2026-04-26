#!/usr/bin/env python3
"""BosskuAI adversarial routing eval.

Unlike eval_workspace.py's routing-fit (which uses prompts that contain the
target skill's own keywords), this eval uses prompts that intentionally avoid
the obvious keywords. It measures whether routing is robust to natural user
phrasing, not just to keyword matches it was tuned against.

A case PASSes if predicted_skill is in `acceptable_skills`. The default
fallback (`bosskuai-workspace-assistant`) is NOT acceptable - that's a miss.
"""
from __future__ import annotations
import argparse, importlib.util, json, sys
from pathlib import Path


def load_eval_workspace(root: Path):
    spec = importlib.util.spec_from_file_location("eval_workspace", root / "scripts/eval_workspace.py")
    mod = importlib.util.module_from_spec(spec)
    sys.modules["eval_workspace"] = mod
    assert spec.loader is not None
    spec.loader.exec_module(mod)
    return mod


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    ap.add_argument("--json", action="store_true")
    ap.add_argument("--threshold", type=float, default=0.75,
                    help="Pass-rate target for green (default 0.75).")
    ap.add_argument("--strict", action="store_true",
                    help="Exit non-zero if pass rate is below threshold. "
                         "Default is diagnostic-only (always exit 0) because this "
                         "eval is designed to expose routing gaps, not gate releases.")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    ew = load_eval_workspace(root)
    idx = ew.load_json(root / "skill-index.json")
    cases = ew.load_json(root / "evals/adversarial-routing-cases.json")

    results, passed = [], 0
    for case in cases:
        route = ew.route_prompt(case["prompt"], idx)
        predicted = route["predicted_skill"]
        ok = predicted in case["acceptable_skills"]
        passed += int(ok)
        results.append({
            "name": case["name"],
            "acceptable": case["acceptable_skills"],
            "predicted": predicted,
            "top_candidates": route["candidates"][:3],
            "pass": ok,
        })

    rate = passed / len(cases) if cases else 0
    report = {
        "adversarial_routing": {
            "passed": passed, "total": len(cases),
            "pass_rate": round(rate, 3),
            "threshold": args.threshold,
            "green": rate >= args.threshold,
            "results": results,
        }
    }

    if args.json:
        print(json.dumps(report, indent=2))
        return (0 if rate >= args.threshold else 1) if args.strict else 0

    print("BosskuAI adversarial routing eval (diagnostic)")
    print(f"Score: {passed}/{len(cases)} ({rate:.0%})  threshold={args.threshold:.0%}")
    print(f"Status: {'GREEN' if rate >= args.threshold else 'RED — known routing gap'}\n")
    for r in results:
        status = "PASS" if r["pass"] else "FAIL"
        top = r["top_candidates"][0]["skill_id"] if r["top_candidates"] else "-"
        print(f"  {status} {r['name']}")
        print(f"       predicted: {r['predicted']}   top-candidate: {top}")
        if not r["pass"]:
            print(f"       acceptable: {r['acceptable']}")
    if rate < args.threshold and not args.strict:
        print("\n(diagnostic mode — exit 0. See docs/adversarial-routing.md for next steps.)")
    return (0 if rate >= args.threshold else 1) if args.strict else 0


if __name__ == "__main__":
    raise SystemExit(main())
