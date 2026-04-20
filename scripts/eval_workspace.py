#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

TOKEN_RE = re.compile(r"[A-Za-z0-9_./:-]{2,}")
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


def run_retrieval_query(root: Path, query: str, limit: int) -> list[dict]:
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
    tokens = TOKEN_RE.findall(text.lower())
    return " ".join(tokens), set(tokens)


def term_matches(normalized_prompt: str, prompt_tokens: set[str], term: str) -> bool:
    term_tokens = TOKEN_RE.findall(term.lower())
    if not term_tokens:
        return False
    if len(term_tokens) == 1:
        return term_tokens[0] in prompt_tokens
    return " ".join(term_tokens) in normalized_prompt


def route_prompt(prompt: str, skill_index: dict) -> str | None:
    normalized_prompt, prompt_tokens = normalize_prompt(prompt)
    core_skills = set(skill_index.get("routing", {}).get("core_skill_ids", []))
    best_skill = None
    best_score = -1.0

    for skill in skill_index.get("skills", []):
        if skill.get("status") == "deprecated_alias":
            continue
        score = 0.0
        skill_id = skill["id"]
        if term_matches(normalized_prompt, prompt_tokens, skill_id):
            score += 6.0
        for phrase in skill.get("triggers", []):
            if term_matches(normalized_prompt, prompt_tokens, phrase):
                score += 3.0 + (len(TOKEN_RE.findall(phrase.lower())) * 0.1)
        for keyword in skill.get("keywords", []):
            if term_matches(normalized_prompt, prompt_tokens, keyword):
                score += 1.0
        if skill_id in core_skills:
            score += 0.05
        if score > best_score:
            best_score = score
            best_skill = skill_id

    return best_skill if best_score > 0 else None


def percent_change(before: int, after: int) -> float:
    if before == 0:
        return 0.0
    return round(((after - before) / before) * 100.0, 2)


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
        predicted = route_prompt(case["prompt"], skill_index)
        passed = predicted == case["expected_skill"]
        routing_passes += int(passed)
        routing_results.append(
            {
                "name": case["name"],
                "expected_skill": case["expected_skill"],
                "predicted_skill": predicted,
                "pass": passed,
            }
        )

    retrieval_results = []
    retrieval_passes = 0
    for case in retrieval_cases:
        hits = run_retrieval_query(root, case["query"], case["top_k"])
        paths = [hit["path"] for hit in hits[: case["top_k"]]]
        passed = case["expected_path"] in paths
        retrieval_passes += int(passed)
        retrieval_results.append(
            {
                "name": case["name"],
                "expected_path": case["expected_path"],
                "top_paths": paths,
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
            "total": len(retrieval_results),
            "score": round(retrieval_passes / max(len(retrieval_results), 1), 3),
            "results": retrieval_results,
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
        print(f"  {status} {result['name']}: expected={result['expected_skill']} predicted={result['predicted_skill']}")
    print()
    print("Retrieval relevance")
    print(f"  score: {report['retrieval_relevance']['passed']}/{report['retrieval_relevance']['total']}")
    for result in retrieval_results:
        status = "PASS" if result["pass"] else "FAIL"
        print(f"  {status} {result['name']}: expected={result['expected_path']}")
        print(f"       top_paths={', '.join(result['top_paths']) if result['top_paths'] else 'none'}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
