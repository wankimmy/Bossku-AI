# BosskuAI Quality Gate Command

Use this command when you want a concise quality decision on the current work before moving on.

## Intent

- combine engineering-delivery review habits with verification
- produce a short go or no-go summary
- make skipped checks and open risks explicit

## Instructions

1. Identify the relevant work scope: file, feature, or current repo state.
2. Apply `bosskuai-engineering-delivery`, and pull in `bosskuai-cybersecurity-risk` or `bosskuai-business-logic-review` when the change touches sensitive areas.
3. Run or summarize the highest-signal checks available for the scope.
4. Review the result for:
   - correctness and regressions
   - security and misuse risk
   - business-logic gaps
   - missing tests or weak verification
5. Return a short gate decision: `PASS`, `PASS WITH RISKS`, or `FAIL`.

## Output

- scope reviewed
- gate decision
- top findings or risks
- missing checks
- recommended next action
