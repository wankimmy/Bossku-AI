---
name: bosskuai-ratchet-loop
description: Use this for measurable improvement loops where every change needs a baseline, metric, test/eval, keep-or-revert decision, and short log entry.
---

# BosskuAI Ratchet Loop

Use this skill when improving prompts, skills, tests, UI conversion, performance, SEO copy, code quality, or any workflow where "better" must be measured.

## Contract

Do not make many broad changes at once. Run a small loop:

1. Define the metric.
2. Capture the baseline.
3. Make one change.
4. Run the same eval/test/check again.
5. Keep the change only if the metric improves or the tradeoff is explicitly accepted.
6. Revert or isolate the change if it makes the result worse.
7. Log the result and next candidate improvement.

## Good Metrics

- test pass/fail
- eval score
- token surface
- load time
- conversion rate proxy
- false positive / false negative count
- lint/security finding count
- review defects found

## Output Shape

```txt
Metric:
Baseline:
Change:
Result:
Decision: keep | revert | needs manual review
Next:
```

## Guardrails

- One meaningful variable per loop.
- Do not claim improvement without the metric result.
- Prefer deterministic checks before subjective judgement.
- If the metric is weak, state that clearly.

## Reference

- `../../references/checklists/ratchet-loop-checklist.md`
