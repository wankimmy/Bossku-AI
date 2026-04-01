# BosskuAI Continuous Learning Command

Use this command after meaningful work, reviews, incidents, or repeated observations to convert lessons into durable repo improvements.

## Meaningfulness gate (run first — binary)

Meaningful = ANY true:
- File created/edited/deleted
- Architectural or product decision made
- Bug root cause identified
- Skill applied non-generically (repo-specific)
- Pattern appeared 2+ times
- Verification gap or risk surfaced

YES → run full triage below.
NO to all → emit `[TASK END] Meaningful: no, Reason: <one line>` and stop.

## Intent

- capture the strongest durable lessons from recent work
- route each lesson to the right artifact
- catch stale or contradictory memory
- avoid silent broad mutations

## Instructions

1. Use `bosskuai-continuous-learning` plus the learning-promotion and continuous-learning checklists.
2. Start with `bash ./ai-assistant/scripts/learning-doctor.sh` when it is available so memory freshness issues are visible first.
3. Review the recent task outcome, findings, verification gaps, and any memory touched.
4. List candidate learnings and decide the strongest fitting artifact for each one.
5. Propose the smallest safe update rather than a broad rewrite.
6. If a lesson is not strong enough yet, defer it explicitly and state what future evidence would justify promotion.

## Output

- signals reviewed
- candidate learnings
- routing decisions
- freshness issues
- smallest safe promotion actions

## Mandatory output format

Emit before freeform text:
```text
[CONTINUOUS LEARNING PASS]
Date: YYYY-MM-DD
Summary: <one line>
Learnings:
  1. <learning> → <memory|checklist|pitfall|playbook|skill|rule|deferred>
Memory updated: <paths, or "none">
Stale entries: <yes — <path> | no>
Continuation consumed: <yes|no|n/a>
Promotion: <one-line description>
Deferred: <list or "none">
```
Copy-paste directly into `learning-log.md`.
