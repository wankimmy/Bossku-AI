# BosskuAI Benchmarks

These are local proxy checks, not a claim that every model response improves.

## Current local evals

Run:

```bash
python3 -S scripts/eval_workspace.py
```

Measures:

- always-loaded prompt surface
- routing-fit on sample prompts
- retrieval relevance against fixture memory files
- simple workflow proxy behavior

## What counts as stronger evidence

Add real task case studies:

| Case | Baseline | BosskuAI profile | Metric |
|---|---|---|---|
| Laravel review | normal prompt | `dev` | defects found, false positives, test notes |
| README rewrite | normal prompt | `core` + human-output | AI phrases removed, specificity added |
| Prompt compression | long rule file | token-saver | token surface before/after |
| Repo onboarding | no layer | `core` | files opened, wrong assumptions, time to first correct map |

## Rules

- Record baseline before changing anything.
- Keep the same task and model where possible.
- Do not report subjective improvement without examples.
- Separate measured results from inferred benefits.

## Expert coverage benchmark

Run:

```bash
python3 -S scripts/eval_expert_coverage.py
```

This validates the expert cofounder task bank in `evals/expert-benchmark-cases.json`.
