#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

TOKEN_RE = re.compile(r"[A-Za-z0-9_./:-]{2,}")
ROUTE_TOKEN_RE = re.compile(r"[A-Za-z0-9]+")
PROMPT_SURFACE_FILES = [
    "AGENTS.md",
    "CLAUDE.md",
    ".codex/AGENTS.md",
    ".claude/rules/bosskuai.md",
    ".cursor/rules/bosskuai.mdc",
]


def repo_root() -> Path:
    return Path(__file__).resolve().parents[1]


def load_json(path: Path) -> object:
    return json.loads(path.read_text(encoding="utf-8"))


def compute_prompt_surface(root: Path) -> dict:
    files = {}
    for rel in PROMPT_SURFACE_FILES:
        text = (root / rel).read_text(encoding="utf-8")
        files[rel] = {
            "lines": len(text.splitlines()),
            "words": len(text.split()),
            "approx_tokens": len(TOKEN_RE.findall(text)),
            "chars": len(text),
        }
    total = {key: sum(item[key] for item in files.values()) for key in ["lines", "words", "approx_tokens", "chars"]}
    return {"files": files, "total": total}


def run_retrieval_query(root: Path, query: str, limit: int, strategy: str = "hybrid") -> list[dict]:
    cmd = [
        sys.executable,
        str(root / "ai-assistant/scripts/vector_memory.py"),
        "--root",
        str(root),
        "--config",
        str(root / "evals/retrieval-fixtures/vector-config.json"),
        "query",
        query,
        "--limit",
        str(limit),
        "--strategy",
        strategy,
        "--json",
    ]
    completed = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return json.loads(completed.stdout)


def sync_retrieval(root: Path) -> None:
    cmd = [
        sys.executable,
        str(root / "ai-assistant/scripts/vector_memory.py"),
        "--root",
        str(root),
        "--config",
        str(root / "evals/retrieval-fixtures/vector-config.json"),
        "sync",
    ]
    subprocess.run(cmd, check=True, capture_output=True, text=True)


def normalize_prompt(text: str) -> tuple[str, set[str]]:
    tokens = ROUTE_TOKEN_RE.findall(text.lower())
    return " ".join(tokens), set(tokens)


def term_matches(normalized_prompt: str, prompt_tokens: set[str], term: str) -> bool:
    term_tokens = ROUTE_TOKEN_RE.findall(term.lower())
    if not term_tokens:
        return False
    if len(term_tokens) == 1:
        return term_tokens[0] in prompt_tokens
    joined = " ".join(term_tokens)
    return joined in normalized_prompt or set(term_tokens).issubset(prompt_tokens)


def route_prompt(prompt: str, skill_index: dict) -> dict:
    normalized_prompt, prompt_tokens = normalize_prompt(prompt)
    routing = skill_index.get("routing", {})
    core_skills = set(routing.get("core_skill_ids", []))
    manual_only = set(routing.get("manual_only_skill_ids", []))
    default_skill = routing.get("default_skill_id")
    no_specialist_min_score = float(routing.get("no_specialist_min_score", 2.4))
    ambiguity_margin = float(routing.get("ambiguity_margin", 1.2))

    candidates = []
    for skill in skill_index.get("skills", []):
        if skill.get("status") == "deprecated_alias":
            continue

        skill_id = skill["id"]
        exact_id = term_matches(normalized_prompt, prompt_tokens, skill_id)
        trigger_hits = sum(1 for phrase in skill.get("triggers", []) if term_matches(normalized_prompt, prompt_tokens, phrase))
        keyword_hits = sum(1 for keyword in skill.get("keywords", []) if term_matches(normalized_prompt, prompt_tokens, keyword))
        matched = exact_id or trigger_hits > 0 or keyword_hits > 0

        score = 0.0
        score += 6.5 if exact_id else 0.0
        score += trigger_hits * 3.2
        score += keyword_hits * 1.05
        if matched and skill_id in core_skills:
            score += 0.2
        if matched and skill_id == default_skill and score > 0:
            score += 0.15

        explicit_manual_signal = exact_id or trigger_hits > 0 or keyword_hits >= 2
        if skill_id in manual_only and not explicit_manual_signal:
            continue
        if not matched or score <= 0:
            continue

        candidates.append(
            {
                "skill_id": skill_id,
                "score": round(score, 3),
                "exact_id": exact_id,
                "trigger_hits": trigger_hits,
                "keyword_hits": keyword_hits,
                "is_core": skill_id in core_skills,
            }
        )

    candidates.sort(
        key=lambda item: (
            item["score"],
            item["trigger_hits"],
            item["keyword_hits"],
            item["is_core"],
        ),
        reverse=True,
    )

    if not candidates or candidates[0]["score"] < no_specialist_min_score:
        return {"predicted_skill": None, "candidates": candidates[:3]}

    best = candidates[0]
    second = candidates[1] if len(candidates) > 1 else None
    if second and best["skill_id"] not in core_skills and (best["score"] - second["score"]) < ambiguity_margin:
        if default_skill:
            return {"predicted_skill": default_skill, "candidates": candidates[:3], "fallback": "ambiguous_non_core"}
        return {"predicted_skill": None, "candidates": candidates[:3], "fallback": "ambiguous_non_core"}

    return {"predicted_skill": best["skill_id"], "candidates": candidates[:3]}


def percent_change(before: int, after: int) -> float:
    if before == 0:
        return 0.0
    return round(((after - before) / before) * 100.0, 2)


def accepted_prediction(case: dict, predicted: str | None) -> bool:
    if "acceptable_skills" in case:
        return predicted in case["acceptable_skills"]
    return predicted == case.get("expected_skill")


def path_rank(paths: list[str], expected_path: str) -> int | None:
    for index, path in enumerate(paths, start=1):
        if path == expected_path:
            return index
    return None


def summarize_hits(hits: list[dict], top_k: int) -> list[str]:
    return [hit["path"] for hit in hits[:top_k]]


def main() -> int:
    parser = argparse.ArgumentParser(description="Run local BosskuAI workspace evals.")
    parser.add_argument("--root", default=str(repo_root()), help="Workspace root.")
    parser.add_argument("--skip-sync", action="store_true", help="Skip vector memory sync before retrieval evals.")
    parser.add_argument("--json", action="store_true", help="Emit JSON output.")
    args = parser.parse_args()

    root = Path(args.root).expanduser().resolve()
    baseline = load_json(root / "evals/baselines/prompt-surface-pre-refactor.json")
    routing_cases = load_json(root / "evals/routing-cases.json")
    retrieval_cases = load_json(root / "evals/retrieval-cases.json")
    workflow_cases = load_json(root / "evals/task-workflow-cases.json")
    skill_index = load_json(root / "skill-index.json")

    if not args.skip_sync:
        sync_retrieval(root)

    prompt_surface = compute_prompt_surface(root)
    before_tokens = baseline["total"]["approx_tokens"]
    after_tokens = prompt_surface["total"]["approx_tokens"]
    token_eval = {
        "before_tokens": before_tokens,
        "after_tokens": after_tokens,
        "delta_tokens": after_tokens - before_tokens,
        "delta_percent": percent_change(before_tokens, after_tokens),
        "pass": after_tokens < before_tokens,
    }

    routing_results = []
    routing_passes = 0
    for case in routing_cases:
        routed = route_prompt(case["prompt"], skill_index)
        predicted = routed["predicted_skill"]
        passed = accepted_prediction(case, predicted)
        routing_passes += int(passed)
        routing_results.append(
            {
                "name": case["name"],
                "expected_skill": case.get("expected_skill"),
                "acceptable_skills": case.get("acceptable_skills"),
                "predicted_skill": predicted,
                "top_candidates": routed["candidates"],
                "pass": passed,
            }
        )

    retrieval_results = []
    retrieval_passes = 0
    top1_passes = 0
    for case in retrieval_cases:
        hybrid_hits = run_retrieval_query(root, case["query"], case["top_k"], strategy="hybrid")
        baseline_hits = run_retrieval_query(root, case["query"], case["top_k"], strategy="semantic-only")
        hybrid_paths = summarize_hits(hybrid_hits, case["top_k"])
        baseline_paths = summarize_hits(baseline_hits, case["top_k"])
        hybrid_rank = path_rank(hybrid_paths, case["expected_path"])
        baseline_rank = path_rank(baseline_paths, case["expected_path"])
        expect_top1 = case.get("expect_top1", True)
        passed = hybrid_rank == 1 if expect_top1 else hybrid_rank is not None
        top1_passes += int(hybrid_rank == 1)
        retrieval_passes += int(passed)
        retrieval_results.append(
            {
                "name": case["name"],
                "expected_path": case["expected_path"],
                "expect_top1": expect_top1,
                "hybrid_top_paths": hybrid_paths,
                "semantic_only_top_paths": baseline_paths,
                "hybrid_rank": hybrid_rank,
                "semantic_only_rank": baseline_rank,
                "improved_vs_semantic_only": baseline_rank is None or (hybrid_rank is not None and hybrid_rank < baseline_rank),
                "pass": passed,
            }
        )

    workflow_results = []
    workflow_passes = 0
    for case in workflow_cases:
        baseline_skill = None
        enabled_skill = route_prompt(case["prompt"], skill_index)["predicted_skill"]
        baseline_hits = run_retrieval_query(root, case["query"], case.get("top_k", 3), strategy="semantic-only")
        enabled_hits = run_retrieval_query(root, case["query"], case.get("top_k", 3), strategy="hybrid")
        baseline_paths = summarize_hits(baseline_hits, case.get("top_k", 3))
        enabled_paths = summarize_hits(enabled_hits, case.get("top_k", 3))
        baseline_rank = path_rank(baseline_paths, case["expected_path"])
        enabled_rank = path_rank(enabled_paths, case["expected_path"])
        skill_ok = enabled_skill == case["expected_skill"]
        retrieval_ok = enabled_rank == 1
        improved_skill = enabled_skill != baseline_skill
        improved_retrieval = baseline_rank is None or (enabled_rank is not None and enabled_rank < baseline_rank)
        passed = skill_ok and retrieval_ok and (improved_skill or improved_retrieval)
        workflow_passes += int(passed)
        workflow_results.append(
            {
                "name": case["name"],
                "baseline": {
                    "predicted_skill": baseline_skill,
                    "retrieval_rank": baseline_rank,
                    "top_paths": baseline_paths,
                },
                "bosskuai_enabled": {
                    "predicted_skill": enabled_skill,
                    "retrieval_rank": enabled_rank,
                    "top_paths": enabled_paths,
                },
                "expected_skill": case["expected_skill"],
                "expected_path": case["expected_path"],
                "improved_skill": improved_skill,
                "improved_retrieval": improved_retrieval,
                "pass": passed,
            }
        )

    report = {
        "prompt_surface": token_eval,
        "routing_fit": {
            "passed": routing_passes,
            "total": len(routing_results),
            "score": round(routing_passes / max(len(routing_results), 1), 3),
            "results": routing_results,
        },
        "retrieval_relevance": {
            "passed": retrieval_passes,
            "top1_passed": top1_passes,
            "total": len(retrieval_results),
            "score": round(retrieval_passes / max(len(retrieval_results), 1), 3),
            "results": retrieval_results,
        },
        "workflow_proxy": {
            "passed": workflow_passes,
            "total": len(workflow_results),
            "score": round(workflow_passes / max(len(workflow_results), 1), 3),
            "results": workflow_results,
        },
    }

    if args.json:
        print(json.dumps(report, indent=2))
        return 0

    print("BosskuAI workspace eval")
    print()
    print("Prompt surface")
    print(f"  before approx tokens: {before_tokens}")
    print(f"  after approx tokens:  {after_tokens}")
    print(f"  delta tokens:         {token_eval['delta_tokens']}")
    print(f"  delta percent:        {token_eval['delta_percent']}%")
    print(f"  pass:                 {'yes' if token_eval['pass'] else 'no'}")
    print()
    print("Routing-fit proxy")
    print(f"  score: {report['routing_fit']['passed']}/{report['routing_fit']['total']}")
    for result in routing_results:
        status = "PASS" if result["pass"] else "FAIL"
        expected = result["acceptable_skills"] if result.get("acceptable_skills") is not None else result["expected_skill"]
        print(f"  {status} {result['name']}: expected={expected} predicted={result['predicted_skill']}")
    print()
    print("Retrieval relevance")
    print(
        f"  score: {report['retrieval_relevance']['passed']}/{report['retrieval_relevance']['total']} "
        f"(top1={report['retrieval_relevance']['top1_passed']}/{report['retrieval_relevance']['total']})"
    )
    for result in retrieval_results:
        status = "PASS" if result["pass"] else "FAIL"
        print(
            f"  {status} {result['name']}: expected={result['expected_path']} "
            f"hybrid_rank={result['hybrid_rank']} semantic_only_rank={result['semantic_only_rank']}"
        )
    print()
    print("Workflow proxy")
    print(f"  score: {report['workflow_proxy']['passed']}/{report['workflow_proxy']['total']}")
    for result in workflow_results:
        status = "PASS" if result["pass"] else "FAIL"
        print(
            f"  {status} {result['name']}: baseline_skill={result['baseline']['predicted_skill']} "
            f"bosskuai_skill={result['bosskuai_enabled']['predicted_skill']} "
            f"baseline_rank={result['baseline']['retrieval_rank']} "
            f"bosskuai_rank={result['bosskuai_enabled']['retrieval_rank']}"
        )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
