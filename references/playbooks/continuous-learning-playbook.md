# Continuous Learning Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this after meaningful work to convert lessons into durable improvements without turning the repo into a noisy archive.

## Workflow

1. Review the recent task, findings, verification gaps, and memory touched.
2. Extract 1 to 3 concrete candidate learnings in one-sentence form.
3. Score each candidate:
   - evidence strength
   - durability
   - repeat likelihood
   - blast radius if forgotten
4. Route each candidate to the strongest artifact:
   - memory for stable project facts
   - learning log for chronological durable lessons
   - bug patterns for recurring or severe failure classes
   - market notes for durable GTM lessons
   - checklist for repeatable verification steps
   - pitfall for recurring traps
   - playbook for reusable workflows
   - skill or rule only when future agent behavior should change broadly
5. Make the smallest safe update and avoid duplicate wording across multiple artifacts.
6. Run `bash ./ai-assistant/scripts/learning-doctor.sh` when available to catch stale counts, contradictory memory, stale continuation state, or empty high-value memory files.
7. If the repo itself changed enough to invalidate current understanding, run `bash ./ai-assistant/scripts/project-understanding.sh` to prepare a safe refresh packet.
7. Record whether the learning was:
   - captured only
   - promoted
   - deferred pending more evidence
   - rejected as too weak or too temporary

## Output expectation

- signals reviewed
- candidate learnings
- routing decisions
- exact proposed updates
- freshness issues
- follow-up threshold for deferred lessons
