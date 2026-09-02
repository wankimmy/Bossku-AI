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
   - `project.md` (`bossku remember --kind project`) for stable project facts
   - `learnings.md` (`--kind learning`) for verified lessons, recurring bugs, and verification results
   - `decisions.md` (`--kind decision`) for choices and their reasons
   - `plans.md` (`--kind plan`) for plans another session may continue
   - checklist for repeatable verification steps
   - pitfall for recurring traps
   - playbook for reusable workflows
   - skill or rule only when future agent behavior should change broadly
5. Make the smallest safe update and avoid duplicate wording across multiple artifacts.
6. Re-read the touched memory file for stale counts, contradictory entries, or a `handoff.md` that is no longer active; append a dated correction rather than rewriting history.
7. If the repo changed enough to invalidate current understanding, rerun `bosskuai-project-understanding` and refresh `project.md`.
8. Record whether the learning was:
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
