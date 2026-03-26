# Verification Playbook

Use this when validating whether a change is actually ready to hand off.

## Workflow

1. Identify the verification steps that matter in the current repo: build, types, lint, tests, coverage, security checks, or runtime smoke checks.
2. Run the highest-signal checks available in a sensible order so early failures stop wasted work.
3. Inspect the resulting diff or changed files for accidental edits and leftover debugging artifacts.
4. Summarize the result as verified, partially verified, or blocked.
5. Call out residual risks and any checks that still need to run elsewhere.

## Output expectation

- verification scope
- checks run
- pass or fail summary
- notable errors or blockers
- residual risks
- handoff readiness
