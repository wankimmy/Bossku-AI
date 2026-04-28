# LLM-Quality Eval

## What this measures

`scripts/eval_llm_quality.py` is the only eval in this repo that grades **actual model answers**. The other evals all check the workspace itself (file presence, keyword routing, must_cover greps). Those are useful regression checks, but they cannot tell you whether the agent answers expert questions well — only this eval can.

It does not call a model itself. It generates the artifacts a human or grading model needs to produce a deterministic, reproducible score.

## Why it ships in scaffolding form

A fully-automated LLM-quality eval would need a grading model wired into the harness. Two reasons we don't ship that here:

1. **Reproducibility.** Different graders (different models, different versions) produce different scores. Bundling one grader into the harness creates a moving target. Externalizing the grader keeps the harness deterministic — same grades in, same scores out, forever.
2. **Honesty.** The harness can score grades it's given; it cannot vouch for the grades. Whoever runs it must own the grader choice.

## The pipeline

Three commands, intended to be run by three different actors:

```text
emit-prompts  ─┐
               ├─→  candidate model writes answers
               │
emit-rubrics  ─┤
               ├─→  grading model (or human) writes grade JSONs
               │
score         ─┘    deterministic report
```

### Step 1 — emit task prompts

```bash
python3 scripts/eval_llm_quality.py --emit-prompts --run-id 2026-04-baseline
```

Writes one `evals/runs/2026-04-baseline/prompts/<id>.txt` per case. Each one is a clean problem statement with no hints.

Feed each `.txt` to the candidate model (Bossku-AI in production config, with all skills available). Save its answer to `evals/runs/2026-04-baseline/answers/<id>.md`. One file per case.

### Step 2 — emit rubric prompts

```bash
python3 scripts/eval_llm_quality.py --emit-rubrics --run-id 2026-04-baseline
```

Writes one `evals/runs/2026-04-baseline/rubrics/<id>.txt` per case. The rubric contains the original task, the per-criterion weights, the must-avoid list, the candidate's answer, and explicit instructions to the grader to return a structured JSON object.

Feed each `.txt` to the grader. Save the JSON output to `evals/runs/2026-04-baseline/grades/<id>.json`. One file per case.

The grader can be:

- A different model from the candidate (preferred — reduces self-grading bias).
- A human who follows the rubric directly.
- An ensemble (run two graders, average their scores) for high-stakes releases.

### Step 3 — score

```bash
python3 scripts/eval_llm_quality.py --score --run-id 2026-04-baseline
```

Reads every grade JSON, validates schema, computes weighted scores, applies hard-fail rules, prints the report.

```text
BosskuAI LLM-quality eval — run 2026-04-baseline
Threshold:  0.70
Graded:     7/7
Passed:     5/7  (pass rate 71%)
Avg score:  0.79

  PASS laravel-double-charge         score=0.85  (11.05/13)
  PASS laravel-tenant-leak           score=0.83  (10.0/12)
  FAIL nuxt-ssr-waterfall            score=0.55  (6.05/11)
  ...
```

## Grade JSON schema

Every file under `grades/` must match:

```json
{
  "id": "laravel-double-charge",
  "criteria": [
    {"weight": 3, "score": 1.0, "note": "explicit timeout/retry_after analysis"},
    {"weight": 3, "score": 0.5, "note": "mentions idempotency but only at one layer"},
    ...
  ],
  "must_avoid_violations": [],
  "answer_excerpt": "..."
}
```

Rules:

- `criteria` length must equal the rubric length and be in the same order.
- `score` must be in `[0.0, 1.0]`. The harness clamps out-of-range values.
- `must_avoid_violations` lists which must-avoid phrases (verbatim from the rubric) the grader actually found in the answer. **Any non-empty list zeros the case.**
- `answer_excerpt` is for the report only; not used in scoring.

## Scoring math

```
case_score = sum(weight_i * score_i) / sum(weight_i)        # in [0, 1]

if must_avoid_violations is non-empty:
    case_score = 0
```

A run is GREEN if `case_score >= --pass-threshold` (default 0.7) for every case.

## What "true 4.5" would look like here

Pass rate ≥ 80% with average score ≥ 0.80 across all marquee skills, using a grading model independent of the candidate, on a run where prompts were not seen during development. The current case bank (`evals/llm-quality-cases.json`) is a starting point — 7 cases is enough to surface obvious regressions, not enough to certify quality. For a release-grade benchmark, expand to 25–40 cases covering each marquee skill at multiple difficulty tiers, and require two independent graders to agree within 0.15 per case.

## Why this matters more than the other evals

The keyword-based routing evals can be gamed by adding keywords. The expert-coverage benchmark can be gamed by adding playbook content matching a `must_cover` list. This eval cannot be gamed by adding keywords or files — only by actually getting better at answering the questions. It is the only eval in the repo that checks whether the depth in the playbooks made it into model answers.
