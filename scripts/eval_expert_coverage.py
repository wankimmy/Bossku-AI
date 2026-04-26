#!/usr/bin/env python3
"""BosskuAI expert coverage eval.

This is not an LLM benchmark. It verifies that the workspace contains enough
specialist guidance to support expert-level answers for the cofounder stack.

It checks:
1. prompt routing reaches the expected skill;
2. referenced playbooks/checklists exist;
3. required coverage terms appear in the relevant skill + references.
"""
from __future__ import annotations

import argparse
import importlib.util
import json
import sys
from pathlib import Path


def load_eval_workspace(root: Path):
    spec = importlib.util.spec_from_file_location("eval_workspace", root / "scripts/eval_workspace.py")
    mod = importlib.util.module_from_spec(spec)
    sys.modules["eval_workspace"] = mod
    assert spec.loader is not None
    spec.loader.exec_module(mod)
    return mod


def read_text(root: Path, rel: str) -> str:
    p = root / rel
    if not p.exists():
        return ""
    return p.read_text(encoding="utf-8", errors="ignore")


def normalize(s: str) -> str:
    return s.lower().replace("-", " ").replace("_", " ")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    ew = load_eval_workspace(root)
    idx = ew.load_json(root / "skill-index.json")
    cases = ew.load_json(root / "evals/expert-benchmark-cases.json")

    results = []
    passed = 0

    for case in cases:
        route = ew.route_prompt(case["prompt"], idx)
        predicted = route["predicted_skill"]
        expected = case["expected_skill"]
        route_ok = predicted == expected

        refs = case.get("expected_references", [])
        refs_ok = all((root / r).exists() for r in refs)

        corpus = read_text(root, f"ai-assistant/skills/{expected}/SKILL.md") + "\n"
        for ref in refs:
            corpus += read_text(root, ref) + "\n"

        corpus_n = normalize(corpus)
        missing_terms = [term for term in case.get("must_cover", []) if normalize(term) not in corpus_n]
        coverage_ok = not missing_terms

        ok = route_ok and refs_ok and coverage_ok
        passed += int(ok)
        results.append({
            "name": case["name"],
            "expected_skill": expected,
            "predicted_skill": predicted,
            "route_pass": route_ok,
            "references_pass": refs_ok,
            "coverage_pass": coverage_ok,
            "missing_terms": missing_terms,
            "pass": ok,
        })

    report = {"expert_coverage": {"passed": passed, "total": len(cases), "results": results}}

    if args.json:
        print(json.dumps(report, indent=2))
        return 0 if passed == len(cases) else 1

    print("BosskuAI expert coverage eval")
    print(f"Score: {passed}/{len(cases)}")
    for result in results:
        status = "PASS" if result["pass"] else "FAIL"
        missing = f" missing={result['missing_terms']}" if result["missing_terms"] else ""
        print(
            f"  {status} {result['name']}: "
            f"expected={result['expected_skill']} predicted={result['predicted_skill']}{missing}"
        )

    return 0 if passed == len(cases) else 1


if __name__ == "__main__":
    raise SystemExit(main())
