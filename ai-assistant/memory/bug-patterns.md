# Bug patterns

Use this file for **durable, recurring failure modes** in this workspace or product — patterns that should change how reviews, tests, or skills are applied across tools and sessions.

## When to add an entry

Add a row when **both** are true:

1. The same class of defect (or near-miss) has shown up **at least twice**, or once with **high severity** (data loss, security, money, widespread outage).
2. The lesson is **actionable** for future work (a specific check, invariant, or file area) — not a one-off typo.

Do **not** use this file for: transient incidents with no repeatable pattern, full postmortems (put those in project docs), or secrets/PII.

## When to promote out

- **Twice in this file** for the same theme → add a bullet to `ai-assistant/references/pitfalls/` (pick the best domain file) or extend a checklist.
- **Stable workflow gap** → propose a skill or playbook update via **rules-distill** / **skill-stocktake**.

## Suggested format (append new blocks)

```markdown
### [Short name] — YYYY-MM

- **Symptom:** what users or ops saw
- **Root cause:** technical or process
- **Invariant to enforce:** one line
- **Where to check:** dirs, endpoints, or test type
```

## Entries

_(None yet — append using the format above.)_
