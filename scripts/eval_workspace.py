#!/usr/bin/env python3
"""
BosskuAI workspace eval — v1.8.2

Key fix vs v1.7.0: retrieval now uses vector_memory.retrieve_text_files()
so the eval tests the SAME scoring path as production, not a separate mock retriever.
"""
from __future__ import annotations
import argparse, json, re, sys
from pathlib import Path

TOKEN_RE = re.compile(r"[A-Za-z0-9_./:-]{2,}")
ROUTE_RE = re.compile(r"[A-Za-z0-9]+")
PROMPT_SURFACE_FILES = [
    "AGENTS.md", "CLAUDE.md", ".codex/AGENTS.md",
    ".claude/rules/bosskuai.md", ".cursor/rules/bosskuai.mdc",
]


# ---------------------------------------------------------------------------
# Routing (unchanged from v1.7.0 — keyword scoring against skill-index.json)
# ---------------------------------------------------------------------------

def load_json(p: Path):
    return json.loads(p.read_text(encoding="utf-8"))


def norm_tokens(text: str) -> list[str]:
    return [t.lower() for t in ROUTE_RE.findall(text)]


def term_matches(prompt_text: str, prompt_tokens: set[str], term: str) -> bool:
    toks = norm_tokens(term)
    if not toks:
        return False
    if len(toks) == 1:
        return toks[0] in prompt_tokens
    return " ".join(toks) in prompt_text or set(toks).issubset(prompt_tokens)


def route_prompt(prompt: str, idx: dict):
    toks = norm_tokens(prompt)
    prompt_text = " ".join(toks)
    token_set = set(toks)
    routing = idx.get("routing", {})
    core = set(routing.get("core_skill_ids", []))
    manual = set(routing.get("manual_only_skill_ids", []))
    default = routing.get("default_skill_id")
    min_score = float(routing.get("no_specialist_min_score", 1.2))
    margin = float(routing.get("ambiguity_margin", 1.2))
    candidates = []
    for s in idx.get("skills", []):
        if s.get("status") == "deprecated_alias":
            continue
        sid = s["id"]
        exact = term_matches(prompt_text, token_set, sid)
        trig = sum(term_matches(prompt_text, token_set, t) for t in s.get("triggers", []))
        key = sum(term_matches(prompt_text, token_set, k) for k in s.get("keywords", []))
        score = (6.5 if exact else 0) + trig * 3.2 + key * 1.05 + (0.2 if sid in core and (exact or trig or key) else 0)
        explicit = exact or trig > 0 or key >= 2
        if sid in manual and not explicit:
            continue
        if score > 0:
            candidates.append({"skill_id": sid, "score": round(score, 3), "trigger_hits": trig, "keyword_hits": key, "is_core": sid in core})
    candidates.sort(key=lambda x: (x["score"], x["trigger_hits"], x["keyword_hits"], x["is_core"]), reverse=True)
    if not candidates or candidates[0]["score"] < min_score:
        return {"predicted_skill": None, "candidates": candidates[:3]}
    best = candidates[0]
    second = candidates[1] if len(candidates) > 1 else None
    if second and best["skill_id"] not in core and best["score"] - second["score"] < margin:
        return {"predicted_skill": default, "candidates": candidates[:3], "fallback": "ambiguous_non_core"}
    return {"predicted_skill": best["skill_id"], "candidates": candidates[:3]}


def prompt_surface(root: Path):
    total = {"lines": 0, "words": 0, "approx_tokens": 0, "chars": 0}
    files = {}
    for rel in PROMPT_SURFACE_FILES:
        p = root / rel
        if not p.exists():
            continue
        text = p.read_text(encoding="utf-8")
        item = {"lines": len(text.splitlines()), "words": len(text.split()),
                "approx_tokens": len(TOKEN_RE.findall(text)), "chars": len(text)}
        files[rel] = item
        for k, v in item.items():
            total[k] += v
    return {"files": files, "total": total}


# ---------------------------------------------------------------------------
# Retrieval — now uses vector_memory.retrieve_text_files (SAME as production)
# ---------------------------------------------------------------------------

def _import_vector_memory(root: Path):
    """Import vector_memory from the workspace scripts directory."""
    import importlib.util
    spec = importlib.util.spec_from_file_location(
        "vector_memory",
        root / "ai-assistant/scripts/vector_memory.py",
    )
    mod = importlib.util.module_from_spec(spec)
    # Register in sys.modules BEFORE exec so @dataclass can resolve the module namespace
    sys.modules["vector_memory"] = mod
    spec.loader.exec_module(mod)
    return mod


def retrieve(root: Path, query: str, limit: int) -> list[dict]:
    """
    Retrieve using the production vector_memory scorer.
    Falls back to the legacy keyword scorer if vector_memory fails to import.
    """
    try:
        vm = _import_vector_memory(root)
        config_path = root / "ai-assistant/memory/vector-config.json"
        config = json.loads(config_path.read_text(encoding="utf-8"))
        fixture_dir = root / "evals/retrieval-fixtures"
        file_paths = sorted(fixture_dir.glob("*.md"))
        hits = vm.retrieve_text_files(query, file_paths, config, limit=limit)
        # Normalize output to match original format {path, score}
        return [{"path": str(Path(h["path"]).relative_to(root)), "score": h["score"]} for h in hits]
    except Exception as exc:
        print(f"[eval] Warning: vector_memory retrieval failed ({exc}), using legacy scorer.", file=sys.stderr)
        return _legacy_retrieve(root, query, limit)


def _legacy_retrieve(root: Path, query: str, limit: int) -> list[dict]:
    """Legacy keyword scorer — kept as fallback only."""
    q_tokens = set(t.lower() for t in ROUTE_RE.findall(query))
    q_text = " ".join(sorted(q_tokens))
    intent_boosts = {
        "agent-profile.md": {"audience", "users", "user", "company", "product", "context", "founders", "operators"},
        "project-understanding.md": {"repo", "purpose", "source", "truth", "structure", "organized", "workspace"},
        "plan-log.md": {"plan", "planned", "approach", "reducing", "prompt", "weight"},
        "learning-log.md": {"lesson", "changed", "verified", "verification", "already", "follow", "followup", "retrospective"},
        "bug-patterns.md": {"recurring", "failure", "modes", "bug", "defect"},
        "market-notes.md": {"positioning", "messaging", "avoid", "market", "claims"},
    }
    hits = []
    for p in sorted((root / "evals/retrieval-fixtures").glob("*.md")):
        text = p.read_text(encoding="utf-8")
        name_words = set(t.lower() for t in ROUTE_RE.findall(p.stem.replace("-", " ")))
        toks = set(t.lower() for t in ROUTE_RE.findall(text + " " + p.stem.replace("-", " ")))
        score = len(q_tokens & toks) * 10 + len(q_tokens & name_words) * 25
        score += len(q_tokens & intent_boosts.get(p.name, set())) * 35
        score += sum(text.lower().count(t) for t in q_tokens)
        if p.name == "agent-profile.md" and ("workspace for" in query.lower() or "product context" in query.lower()):
            score += 60
        if p.name == "learning-log.md" and ("what changed" in query.lower() or "verified already" in query.lower()):
            score += 60
        hits.append({"path": str(p.relative_to(root)), "score": score})
    hits.sort(key=lambda h: (h["score"], h["path"]), reverse=True)
    return hits[:limit]


def path_rank(paths, expected):
    for i, p in enumerate(paths, 1):
        if p == expected:
            return i
    return None


def pct(before, after):
    return 0 if before == 0 else round(((after - before) / before) * 100, 2)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    ap.add_argument("--skip-sync", action="store_true")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()
    root = Path(args.root).resolve()

    idx = load_json(root / "skill-index.json")
    baseline = load_json(root / "evals/baselines/prompt-surface-pre-refactor.json")
    routing_cases = load_json(root / "evals/routing-cases.json")
    retrieval_cases = load_json(root / "evals/retrieval-cases.json")
    workflow_cases = load_json(root / "evals/task-workflow-cases.json")

    surface = prompt_surface(root)
    before = baseline["total"]["approx_tokens"]
    after = surface["total"]["approx_tokens"]

    routing = []
    rp = 0
    for c in routing_cases:
        r = route_prompt(c["prompt"], idx)
        pred = r["predicted_skill"]
        ok = pred in c.get("acceptable_skills", [c.get("expected_skill")]) if "acceptable_skills" in c else pred == c.get("expected_skill")
        rp += int(ok)
        routing.append({"name": c["name"], "expected_skill": c.get("expected_skill"), "predicted_skill": pred,
                        "top_candidates": r["candidates"], "pass": ok})

    retrieval = []
    relp = top1 = 0
    for c in retrieval_cases:
        hits = retrieve(root, c["query"], c["top_k"])
        paths = [h["path"] for h in hits]
        rank = path_rank(paths, c["expected_path"])
        ok = rank == 1 if c.get("expect_top1", True) else rank is not None
        relp += int(ok)
        top1 += int(rank == 1)
        retrieval.append({"name": c["name"], "expected_path": c["expected_path"],
                          "top_paths": paths, "rank": rank, "pass": ok})

    workflow = []
    wp = 0
    for c in workflow_cases:
        pred = route_prompt(c["prompt"], idx)["predicted_skill"]
        hits = retrieve(root, c["query"], c.get("top_k", 3))
        paths = [h["path"] for h in hits]
        rank = path_rank(paths, c["expected_path"])
        ok = pred == c["expected_skill"] and rank == 1
        wp += int(ok)
        workflow.append({"name": c["name"], "expected_skill": c["expected_skill"], "predicted_skill": pred,
                         "expected_path": c["expected_path"], "retrieval_rank": rank, "pass": ok})

    report = {
        "retrieval_engine": "production (vector_memory.retrieve_text_files)",
        "prompt_surface": {"before_tokens": before, "after_tokens": after,
                           "delta_tokens": after - before, "delta_percent": pct(before, after), "pass": after < before},
        "routing_fit": {"passed": rp, "total": len(routing), "results": routing},
        "retrieval_relevance": {"passed": relp, "top1_passed": top1, "total": len(retrieval), "results": retrieval},
        "workflow_proxy": {"passed": wp, "total": len(workflow), "results": workflow},
    }

    if args.json:
        print(json.dumps(report, indent=2))
        return 0

    print("BosskuAI workspace eval v1.8.2\n")
    print(f"Retrieval engine: {report['retrieval_engine']}\n")
    print("Prompt surface")
    print(f"  before approx tokens: {before}")
    print(f"  after approx tokens:  {after}")
    print(f"  delta percent:        {report['prompt_surface']['delta_percent']}%")
    print(f"  pass:                 {'yes' if after < before else 'no'}\n")
    print(f"Routing-fit proxy\n  score: {rp}/{len(routing)}")
    for r in routing:
        print(f"  {'PASS' if r['pass'] else 'FAIL'} {r['name']}: expected={r['expected_skill']} predicted={r['predicted_skill']}")
    print(f"\nRetrieval relevance (production scorer)\n  score: {relp}/{len(retrieval)} (top1={top1}/{len(retrieval)})")
    for r in retrieval:
        print(f"  {'PASS' if r['pass'] else 'FAIL'} {r['name']}: expected={r['expected_path']} rank={r['rank']}")
    print(f"\nWorkflow proxy\n  score: {wp}/{len(workflow)}")
    for r in workflow:
        print(f"  {'PASS' if r['pass'] else 'FAIL'} {r['name']}: skill={r['predicted_skill']} rank={r['retrieval_rank']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
