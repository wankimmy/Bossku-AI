---
name: bosskuai-continuous-learning
description: Use this after meaningful tasks, reviews, incidents, or repeated observations to triage what was learned, decide the strongest durable artifact, and propose the smallest safe promotion update without silently mutating the workspace.
---

# BosskuAI Continuous Learning

Use this skill when work has produced a lesson that should improve future sessions instead of disappearing into chat history.

## How this differs from nearby skills

- **`bosskuai-skill-stocktake`**: audits the quality and overlap of the skill system itself. This skill triages lessons produced by recent work and decides where they should live.
- **`bosskuai-rules-distill`**: promotes repeated cross-cutting principles into shared rules. This skill works one level earlier by classifying new learnings and routing them to the right artifact.
- **`bosskuai-context-limit-continuation`**: preserves unfinished task state. This skill captures durable lessons after meaningful work is complete or after a significant review.

## Mindset

- Continuous learning should be deliberate, evidence-based, and reversible.
- Prefer the strongest fitting artifact instead of dumping everything into memory.
- Do not silently mutate skills, rules, or memory just because a task ended.
- The best promotion is the smallest one that will change future behavior reliably.

## When to use

Use this skill when any of the following is true:

- a meaningful task just finished
- a review uncovered a repeatable failure mode
- a workflow worked well enough to reuse
- memory looks stale or contradictory
- the repo learned something that should outlive the current session

## Triage questions

For each candidate learning, answer:

1. **What happened?** A fact, decision, failure, or workflow improvement.
2. **What evidence supports it?** Code, docs, diffs, incidents, repeated usage, or verified outcomes.
3. **How durable is it?** Likely to stay useful across future sessions?
4. **What behavior should change?** Memory recall, verification, warnings, workflow, or core rules.
5. **What is the smallest artifact that will enforce that change?**

## Artifact routing

| Artifact | Use when | Typical examples |
|----------|----------|------------------|
| **Memory** | Durable project fact or convention | repo purpose, preferred verification path, stable team constraint |
| **Learning log** | Chronological durable lesson or decision with promotion status | "we added a new workflow and should review its impact later" |
| **Bug patterns** | Recurring defect class or high-severity near-miss | repeated auth regression, money bug, stale cache invalidation |
| **Market notes** | Durable GTM / competitor / pricing learning | recurring buyer objection, stable packaging pattern |
| **Checklist** | Repeatable verification step | "always verify X when touching billing webhooks" |
| **Pitfall** | Recurring trap that needs warning | hidden edge case that keeps reappearing |
| **Playbook** | Reusable workflow | safe rollout steps, review flow, migration flow |
| **Skill** | Repeated reasoning workflow that changes how the assistant works | new audit lens, new structured decision flow |
| **Rule / ADR** | Cross-cutting universal behavior or explicit policy decision | model split, memory policy, approval rule |

## Workflow

1. **Collect signals**
   - Look at the finished task, review findings, memory changes, and any incident or bug notes.
   - Prefer evidence over impressions.
2. **List candidate learnings**
   - Keep each candidate to one sentence.
   - Merge duplicates before promoting anything.
3. **Classify the learning**
   - Use the learning-promotion and continuous-learning checklists.
   - Decide whether the lesson is local, reusable, repeated, or universal.
4. **Choose the target artifact**
   - Pick the strongest fitting artifact, not the easiest one.
   - If no promotion is justified yet, record it in `learning-log.md` with a review status.
5. **Propose the smallest safe change**
   - Draft the exact note, checklist bullet, pitfall line, playbook addition, or skill change.
   - Do not silently apply broad rewrites.
6. **Check freshness**
   - If memory contradicts the current repo, fix or flag the stale entry.
   - If the repo uses `ai-assistant/scripts/learning-doctor.sh`, run it for a quick hygiene pass.
7. **Record promotion status**
   - Mark whether the lesson was captured, promoted, deferred, or rejected.
   - If deferred, say what future signal would justify stronger promotion.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not promote vague advice like "be more careful."
- Do not create a new skill when a checklist, pitfall, or playbook is enough.
- Do not store secrets, customer-specific confidential data, or transient debug chatter in memory.
- Do not treat one-off observations as universal rules.
- Do not leave contradictory counts, stale repo facts, or consumed continuation state unaddressed.

## Output format

```text
Signals reviewed:
  [source or task outcome]

Candidate learnings:
  [learning] — Evidence: [source] — Durability: [high/medium/low]

Routing decisions:
  [learning] — Target artifact: [memory / learning-log / checklist / pitfall / playbook / skill / rule / ADR]
  Why: [short reason]
  Smallest safe update: [exact proposed change]
  Status: [apply now / defer / reject]

Freshness issues:
  [stale memory / contradictory note / empty artifact worth filling]

Next promotion actions:
  1. [smallest high-value update]
  2. [next]
```

## References

- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/checklists/continuous-learning-checklist.md`
- `../../references/playbooks/continuous-learning-playbook.md`
- `../../memory/learning-log.md`
- `../../memory/bug-patterns.md`
- `../../memory/market-notes.md`
