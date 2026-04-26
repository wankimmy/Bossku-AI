# Adversarial Routing Eval

## What this measures

`scripts/eval_adversarial_routing.py` runs a small set of prompts that describe **symptoms in plain user language** (e.g. "we double-charge customers when the worker retries") instead of **skill jargon** ("idempotent job retry safety").

The existing routing eval in `eval_workspace.py` uses prompts written to contain the same trigger keywords that are listed in `skill-index.json`. That makes it a useful regression check — it catches the case where someone breaks the keyword matcher — but it does **not** measure whether routing handles natural problem statements.

The adversarial eval closes that observability gap.

## Why it ships RED

As of v1.8.3, this eval scores 0/8. That is not a regression introduced by this release — the router has always been keyword-driven; v1.8.3 is just the first release that surfaces the gap with a benchmark.

By default this eval runs in **diagnostic mode** and always exits 0, so it does not block CI. Use `--strict` if you want it to gate.

```bash
python3 -S scripts/eval_adversarial_routing.py            # diagnostic, exit 0
python3 -S scripts/eval_adversarial_routing.py --strict   # gate, exit non-zero on RED
```

## How to actually close the gap

Three approaches, in increasing order of effort and effectiveness.

### 1. Add symptom phrases to skill triggers (cheap, partial)

For each failing case, look at what the user actually wrote, and add a couple of representative *symptom* phrases as triggers in `skill-index.json`. Examples:

- For `bosskuai-laravel-development` add triggers like `memory leak under load`, `worker retries`, `transaction rollback emails`.
- For `bosskuai-vps-docker-deployment` add `502 on first deploy`, `container can't reach database`.

This is an honest improvement only as long as the new triggers describe symptoms in real user terms. If they're just keyword stuffing, the adversarial eval will score higher without the routing actually being smarter — exactly the failure mode this eval was designed to catch.

### 2. Add a small sentence-embedding fallback

Keyword routing handles narrow, jargon-rich prompts well. For prompts where keyword routing scores below `no_specialist_min_score`, fall back to nearest-neighbor retrieval over the SKILL.md descriptions using a small local embedding model (the same one used by `vector_memory`).

This catches paraphrased prompts without inflating the keyword index.

### 3. Use the model itself as the router

For any meaningful task, ask the model to nominate the primary and at-most-one secondary skill given the SKILL.md descriptions. This is the highest-quality option but adds one model call per task. Worth it for high-stakes flows; overkill for trivial ones.

## How to extend the benchmark

`evals/adversarial-routing-cases.json` is intentionally small (8 cases). Add more by following these rules so the eval stays honest:

1. **Symptom, not solution.** Describe what the user observes, not the technique. "Customers report seeing other companies' invoices" — not "broken access control across tenants."
2. **Avoid skill name and trigger words.** Read the relevant skill's `keywords` and `triggers` in `skill-index.json` and remove any that appear in your prompt.
3. **Multiple acceptable skills.** Real problems often have several valid routings. List 2–3 acceptable skills so the eval doesn't penalize a reasonable second choice.
4. **No prompt should require world knowledge the agent wouldn't have.** Keep the case grounded in observable evidence.

## When this eval can be promoted to a gate

When the routing system has been actually upgraded (option 2 or 3 above) and the score reaches the threshold (default 75%), wire `--strict` into CI alongside the other validations.
