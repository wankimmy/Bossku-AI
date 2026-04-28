#!/usr/bin/env python3
"""BosskuAI generalization routing eval.

Companion to eval_adversarial_routing.py. The original adversarial cases were
used to design the symptom triggers in skill-index.json. This eval uses
*different* fresh symptom-language cases that were NOT used to design the
triggers, so it measures whether the triggers actually generalize or just
pass the cases they were tuned against.

A meaningful release improvement should move both the original adversarial
score AND this generalization score up. If only the original moves, the
triggers are overfitted.
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
    ap.add_argument("--threshold", type=float, default=0.75)
    ap.add_argument("--strict", action="store_true",
                    help="Exit non-zero if pass rate is below threshold.")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    ew = load_eval_workspace(root)
    idx = ew.load_json(root / "skill-index.json")
    cases = ew.load_json(root / "evals/adversarial-routing-generalization.json")

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
        "generalization_routing": {
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

    print("BosskuAI generalization routing eval (fresh cases)")
    print(f"Score: {passed}/{len(cases)} ({rate:.0%})  threshold={args.threshold:.0%}")
    print(f"Status: {'GREEN' if rate >= args.threshold else 'RED'}\n")
    for r in results:
        status = "PASS" if r["pass"] else "FAIL"
        top = r["top_candidates"][0]["skill_id"] if r["top_candidates"] else "-"
        print(f"  {status} {r['name']}")
        print(f"       predicted: {r['predicted']}   top-candidate: {top}")
        if not r["pass"]:
            print(f"       acceptable: {r['acceptable']}")
    if rate < args.threshold and not args.strict:
        print("\n(diagnostic mode — exit 0.)")
    return (0 if rate >= args.threshold else 1) if args.strict else 0


if __name__ == "__main__":
    raise SystemExit(main())
