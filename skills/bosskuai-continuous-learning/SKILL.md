---
name: bosskuai-continuous-learning
description: Use this after meaningful tasks, reviews, incidents, or repeated observations to decide the smallest durable artifact that should capture the lesson and keep future sessions from relearning it.
---

# BosskuAI Continuous Learning

Use this skill when recent work produced a lesson worth keeping beyond the current chat.

## Meaningfulness gate

- Meaningful if any of these are true: files changed, a decision was made, a bug/risk was found, a pattern repeated, or a workflow improvement emerged.
- Trivial if all are false. In that case, stop after noting there was no durable delta.

## Routing ladder

- `plan-log.md`: compact pre-execution plan worth retrieving before or during execution
- `learning-log.md`: default chronological handoff
- `agent-profile.md` / `project-understanding.md`: durable workspace facts
- `bug-patterns.md`: repeated defect class or high-severity near miss
- `market-notes.md`: durable GTM or competitor lesson
- `instincts/`: atomic behavioral lesson with a confidence score (see below)
- checklist / pitfall / playbook / skill / rule: when the lesson should change future behavior beyond one repo note

## Instinct model (confidence-weighted micro-lessons)

When a lesson is a small repeatable behavior rather than a fact or a log entry, store it as an **instinct**: one trigger, one action, confidence-weighted. Adapted from ECC continuous-learning-v2.

One file per instinct in `.bossku/memory/instincts/`, named by id:

```yaml
---
id: prefer-runtime-core-blocks
trigger: "when adding or editing a pipeline agent persona"
confidence: 0.7   # 0.3 tentative → 0.9 near certain
domain: workflow  # code-style | testing | git | debugging | workflow | ops
scope: project    # project (default) or global (true in 2+ projects)
evidence: "2026-05-29 persona token audit: full-file injection cost 6,801 tokens; runtime-core cut it 79%"
---
Action: include a <!-- runtime-core:start/end --> block; verify with bosskuai:sync-personas --dry-run.
```

Rules:

- **Atomic** — one trigger, one action; split anything compound.
- **Confidence moves on evidence** — raise by ~0.1 when the instinct is confirmed again, lower when contradicted; delete below 0.3.
- **Promote at high confidence** — an instinct that stays ≥0.8 and keeps firing should graduate up the ladder into a checklist, rule, or skill section; record the promotion and remove the instinct.
- **Scope before sharing** — keep instincts project-scoped by default; mark `scope: global` only when the same behavior proved out in 2+ repos.
- **Runtime parallel** — the in-app equivalent is `LearningEngine` pattern/failure/preference events scored by `PostMemoryEvaluationService`; do not duplicate what the app already captures, but an app-side failure pattern may justify an editor-side instinct.

## Workflow

1. Gather evidence from the finished task, diff, tests, review findings, and memory state.
2. Write each candidate learning in one sentence.
3. Choose the smallest artifact that will reliably change future behavior.
4. Keep pre-execution intent in `plan-log.md`; keep post-execution outcomes and lessons in `learning-log.md` when no stronger promotion is justified yet.
5. Fix stale or contradictory memory if discovered.
6. If indexed memory files changed, run `python3 ./ai-assistant/scripts/vector_memory.py sync`.
7. Record whether the learning was applied, deferred, or rejected.

## Guardrails

- Do not promote vague advice.
- Do not store secrets, sensitive customer data, or transient debug chatter in memory.
- Do not create a new skill when a checklist, pitfall, or short memory note is enough.
- Do not turn one-off observations into universal rules.

## Output

Return:

- signals reviewed
- candidate learnings
- chosen artifact for each learning
- smallest safe update
- freshness issues found
- next promotion actions

## References

- `../../references/memory-first-handoff-protocol.md`
- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/checklists/continuous-learning-checklist.md`
- `../../references/playbooks/continuous-learning-playbook.md`
- `.bossku/memory/plans.md`
- `.bossku/memory/learnings.md`
- `.bossku/memory/learnings.md`
- `.bossku/memory/project.md`
