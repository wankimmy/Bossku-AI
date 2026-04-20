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
- checklist / pitfall / playbook / skill / rule: when the lesson should change future behavior beyond one repo note

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
- `../../memory/plan-log.md`
- `../../memory/learning-log.md`
- `../../memory/bug-patterns.md`
- `../../memory/market-notes.md`
