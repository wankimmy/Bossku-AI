---
name: bosskuai-continuous-learning
description: Use this after meaningful tasks, reviews, incidents, or repeated observations to decide the smallest durable artifact that should capture the lesson and keep future sessions from relearning it.
---

# BosskuAI Continuous Learning

Use this skill when recent work produced a lesson worth keeping beyond the current chat.

## How this differs from nearby skills

- **`bosskuai-permanent-memory-orchestration`**: the mechanics of `.bossku/memory/`, `bossku remember`, and the Obsidian export; this skill decides what deserves to go there.
- **`bosskuai-rules-distill`**: promotes principles seen in 3+ places into rules; this skill handles a single fresh lesson.
- **`bosskuai-skill-creator`**: builds or revises a skill once this skill decides a skill is the right artifact.

## Meaningfulness gate

- Meaningful if any of these are true: files changed, a decision was made, a bug/risk was found, a pattern repeated, or a workflow improvement emerged.
- Trivial if all are false. In that case, stop after noting there was no durable delta.

## Routing ladder (smallest artifact that changes future behavior)

| Lesson looks like | Artifact |
|---|---|
| a plan another session may continue | `bossku remember --kind plan` → `.bossku/memory/plans.md` |
| a verified outcome, recurring bug, or verification result | `bossku remember --kind learning` → `.bossku/memory/learnings.md` |
| a choice worth defending later | `bossku remember --kind decision` → `.bossku/memory/decisions.md` |
| a durable fact about the repo, stack, or users | `bossku remember --kind project` → `.bossku/memory/project.md` |
| a small repeatable behavior with uncertain confidence | instinct file in `.bossku/memory/instincts/` (below) |
| a repeatable verification step, recurring trap, or reusable procedure | checklist, pitfall, or playbook under BosskuAI `references/` |
| a change to how the assistant works across many tasks | skill (`bosskuai-skill-creator`) or rule (`bosskuai-rules-distill`) |

`bossku remember` redacts secrets and exports the four kind files to the Obsidian vault when one is configured, so a memory write is also the Obsidian sync.

## Instinct model (confidence-weighted micro-lessons)

When a lesson is a small repeatable behavior rather than a fact or a log entry, store it as an **instinct**: one trigger, one action, confidence-weighted. Adapted from ECC continuous-learning-v2.

One file per instinct in `.bossku/memory/instincts/`, named by id:

```yaml
---
id: rebuild-index-after-skill-edit
trigger: "when a SKILL.md name, description, or frontmatter changes"
confidence: 0.8   # 0.3 tentative → 0.9 near certain
domain: workflow  # code-style | testing | git | debugging | workflow | ops
scope: project    # project (default) or global (true in 2+ projects)
evidence: "2026-08-18 validate failed on index fingerprint drift after a description edit"
---
Action: run `python -m bossku skills index --root .` then `python -m bossku validate --root .` before declaring done.
```

Rules:

- **Atomic** — one trigger, one action; split anything compound.
- **Confidence moves on evidence** — raise by ~0.1 when the instinct is confirmed again, lower when contradicted; delete below 0.3.
- **Promote at high confidence** — an instinct that stays ≥0.8 and keeps firing should graduate up the ladder into a checklist, rule, or skill section; record the promotion and remove the instinct.
- **Scope before sharing** — keep instincts project-scoped by default; mark `scope: global` only when the same behavior proved out in 2+ repos.
- Instincts are not exported to Obsidian; promote them if they need to travel.

## Workflow

1. Gather evidence from the finished task, diff, tests, review findings, and memory state.
2. Write each candidate learning in one sentence.
3. Choose the smallest artifact from the ladder that will reliably change future behavior.
4. Write it: `bossku remember --project . --kind <kind> "<one to three lines>"` for memory kinds; edit the file directly for instincts and references.
5. Fix stale or contradictory memory if discovered (append a dated correction).
6. Record whether the learning was applied, deferred, or rejected, and what evidence would justify promotion later.

## Guardrails

- Do not promote vague advice.
- Do not store secrets, sensitive customer data, or transient debug chatter in memory.
- Do not create a new skill when a checklist, pitfall, or short memory note is enough.
- Do not turn one-off observations into universal rules.
- Do not write the same lesson into two artifacts; the weaker one should point at the stronger.

## Output

Return:

- signals reviewed
- candidate learnings
- chosen artifact for each learning
- smallest safe update (and the `bossku remember` result, including export status)
- freshness issues found
- next promotion actions

## References

- `../../references/memory-first-handoff-protocol.md`
- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/checklists/continuous-learning-checklist.md`
- `../../references/playbooks/continuous-learning-playbook.md`
- `../../docs/memory.md`
