---
name: bosskuai-bug-finding
description: Use this for bug hunts, regression analysis, suspicious changes, failure-path review, and finding likely defects before or after shipping.
---

# BosskuAI Bug Finding

Use this skill when the goal is to find what is wrong or what is likely to break.

## How this differs from nearby skills

- **`bosskuai-rigorous-code-review`**: reviews a diff for quality across all dimensions; this skill focuses specifically on locating defects and failure modes.
- **`bosskuai-cybersecurity-risk`**: focuses on security threat surfaces; this skill focuses on correctness defects, logic errors, and runtime failures.
- **`bosskuai-business-logic-review`**: validates rules and invariants; load alongside this skill if bugs may originate from misencoded business rules.
- **`bosskuai-root-cause-investigation`**: uses logs, DB state, queues, webhooks, and operational evidence to confirm why a real incident happened after the code path is traced.

## Bug pattern taxonomy

Before tracing code, scan for known high-yield patterns:

| Category | What to look for |
|----------|-----------------|
| **Null / undefined** | Missing guards, optional chaining absent, uninitialized state |
| **Off-by-one** | Loop bounds, slice/splice indices, pagination offsets, date ranges |
| **Concurrency / race** | Shared mutable state, async without await, parallel writes, stale closures |
| **State mutation** | Direct mutation of shared state, missing immutability, unexpected side effects |
| **Boundary / overflow** | Max length not enforced, integer overflow, float precision, empty array |
| **Auth / permissions** | Missing authorization checks, privilege escalation, confused deputy |
| **Retry / idempotency** | Double-submit, non-idempotent POST retried, stale cached response |
| **Error swallowing** | Catch blocks that discard errors, silent fallbacks that mask failures |
| **Time / timezone** | Daylight saving edge, UTC vs local mismatch, expired TTL logic |
| **External dependency** | Assumed availability, no timeout, no retry budget, breaking API contract |

## Workflow

### Phase 1 — Understand the failure

1. Collect all available evidence: error message, stack trace, log output, user description, reproduction steps, environment.
2. State the expected behavior vs the actual observed behavior. These are different things — do not conflate them.
3. Classify failure type: crash, wrong output, missing output, intermittent, performance, data corruption, security bypass.

### Phase 2 — Trace the execution path

4. Start from the entry point (API endpoint, UI action, cron trigger, event handler).
5. Follow the real execution path: entry → validation → business logic → data access → response/side effects.
6. At each step: what is the expected input? what is the actual input? what could differ?
7. Identify where the invariant first breaks — the earliest point where the data or state diverges from expectation.

### Phase 3 — Apply the bug pattern scan

8. Scan the traced path using the bug pattern taxonomy above.
9. Pay extra attention to: error handling paths, concurrent code, external calls, and data transformation boundaries.
10. Check adjacent code — bugs often exist one level up or down from where the symptom appears.

### Phase 4 — Score findings

For each finding, score:
- **Severity**: Critical (data loss / security) → High (incorrect core behavior) → Medium (degraded behavior) → Low (cosmetic / edge case)
- **Reproducibility**: Always → Often (>50%) → Rarely (<10%) → Unknown
- **Evidence**: Confirmed (from code trace) vs Inferred (suspected from pattern)

### Phase 5 — Identify test gaps

11. What test would have caught this bug? Does it exist? If not, name the missing test.
12. What else in the same path is currently untested and could harbor similar defects?

### Phase 6 — Recommend the fix

13. Recommend the **smallest safe change** that closes the real failure boundary — not just the symptom.
14. If the root cause is structural, flag it and recommend whether a local patch is sufficient or a larger fix is needed.
15. State what must be verified after the fix.

## Guardrails

- Do not fix the symptom without tracing to the root cause — symptom fixes mask bugs, they don't close them.
- Do not assume intermittent bugs are non-reproducible — they are usually race conditions or state corruption.
- Do not mark a finding as "Confirmed" unless you have traced it in code. Distinguish inferred patterns from confirmed defects.
- Do not over-fix. The goal is the smallest safe change, not a refactor opportunity.

## Output format

```
Failure summary:
  Expected: [behavior]
  Actual: [behavior]
  Type: [crash / wrong output / missing output / intermittent / data corruption / security bypass]

Execution path traced:
  [entry point] → [steps] → [failure boundary]

Findings:
  [ID] — [description] — Severity: [C/H/M/L] — Reproducibility: [Always/Often/Rarely/Unknown] — Evidence: [Confirmed/Inferred]
    Root cause: [specific code location and why it breaks]
    Fix: [smallest safe change]
    Verify by: [what to check after fix]

Test gaps:
  [missing test that would have caught this] — [location to add it]

Structural risk:
  [if the fix is only a patch and a deeper issue remains, flag it here]
```

## References

- `../../references/playbooks/bug-finding-playbook.md`
- `../../references/checklists/bug-finding-checklist.md`
