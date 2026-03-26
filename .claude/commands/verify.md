# BosskuAI Verify Command

Use this command after implementation, refactoring, or review work to validate the current state before handoff.

## Intent

- determine the relevant repo-specific checks
- run the highest-signal verification steps available
- stop early on major failures
- summarize what is verified and what is still unknown

## Instructions

1. Use the verification playbook and checklist under `ai-assistant/references/`.
2. Detect the relevant verification surface for the current repo, such as build, type checks, lint, tests, coverage, security checks, or smoke tests.
3. Run the checks in a sensible order, stopping early if a blocker makes later steps low-value.
4. Inspect changed files or diff for unintended changes, debug leftovers, or obvious secret exposure.
5. Summarize the result as `PASS`, `PARTIAL`, or `FAIL`.
6. Clearly separate:
   - checks run
   - checks skipped
   - blockers
   - residual risks

## Output

- verification scope
- checks run and results
- skipped or blocked checks
- residual risks
- handoff readiness
