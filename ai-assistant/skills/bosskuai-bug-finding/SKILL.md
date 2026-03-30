---
name: bosskuai-bug-finding
description: Use this for bug hunts, regression analysis, suspicious changes, failure-path review, finding likely defects before shipping, and deep incident investigation using logs, DB state, queues, and runtime evidence.
---

# BosskuAI Bug Finding

Use this skill when the goal is to find what is wrong or what is likely to break. Two modes:
- **Standard mode** — trace defects from code paths and patterns alone.
- **Deep investigation mode** — correlate code with runtime evidence (logs, DB state, queues, webhooks) when code alone cannot explain the failure.

## How this differs from nearby skills

- **`bosskuai-rigorous-code-review`**: reviews a diff for quality across all dimensions; this skill focuses specifically on locating defects and failure modes.
- **`bosskuai-cybersecurity-risk`**: focuses on security threat surfaces; this skill focuses on correctness defects, logic errors, and runtime failures.
- **`bosskuai-business-logic-review`**: validates rules and invariants; load alongside this skill if bugs may originate from misencoded business rules.

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

## Standard workflow

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

## Deep investigation mode

Use when: the bug cannot be explained from code alone and you need to correlate **business logic**, **runtime evidence**, and **system state** — i.e. real incidents with DB rows, logs, queues, webhooks, or external side effects.

### Mindset

- The symptom is rarely the root cause.
- Production truth often lives across code, DB rows, logs, jobs, caches, and external systems together.
- Business-logic bugs are often silent data/state bugs before they are visible UI bugs.
- Read-only evidence collection is the default. Be careful with production data, secrets, and PII.

### Evidence surfaces to inspect

Use the minimum relevant set:

- application logs
- job / queue / worker logs
- web server / proxy logs
- DB records and timestamps
- state-machine transitions
- cron / scheduled task behavior
- webhook delivery history
- cache contents or invalidation behavior
- third-party API responses or dashboards
- deployment / config changes around the incident window

### Deep investigation workflow

1. **Define the incident clearly** — expected behavior, actual behavior, impacted entity/user segment, timeframe, environment.
2. **Trace the business flow** — entry point → validations → business rules → data writes → async jobs / webhooks → downstream side effects.
3. **Build the evidence timeline** — user action or triggering event → request log or job start → DB state changes → external callbacks or failures → final wrong state or user-visible symptom.
4. **Inspect data state directly** — compare expected vs actual row state; check status fields, timestamps, foreign keys, retry counts, idempotency keys, soft deletes, and audit history; look for partial writes, stale rows, missing transitions, or duplicate records.
5. **Inspect logs and operational traces** — correlate by request ID, entity ID, job ID, idempotency key, or user ID; find the earliest divergence; pay attention to silent retries, swallowed exceptions, timeout fallbacks, and background workers.
6. **Test the business invariant** — what rule should have prevented this? Was the rule missing, bypassed, racing, or operating on stale data?
7. **Classify the root cause** — business rule encoded incorrectly / missing validation or authorization / race condition or transaction gap / retry/idempotency failure / stale cache / webhook or async handoff failure / bad migration or data repair / config or environment drift.
8. **Recommend the smallest safe fix** — immediate containment, durable code or schema fix, missing test/monitor/alert, data repair steps if required.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not fix the symptom without tracing to the root cause — symptom fixes mask bugs, they don't close them.
- Do not assume intermittent bugs are non-reproducible — they are usually race conditions or state corruption.
- Do not mark a finding as "Confirmed" unless you have traced it in code. Distinguish inferred patterns from confirmed defects.
- Do not over-fix. The goal is the smallest safe change, not a refactor opportunity.
- Do not mutate production data or replay jobs unless explicitly asked and it is safe to do so.
- Do not stop at "code looks fine" when DB state or logs contradict that conclusion.

## Output format

**Standard mode:**
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

**Deep investigation mode:**
```
Incident summary:
  Expected: [behavior]
  Actual: [behavior]
  Scope: [who/what is affected]
  Time window: [when]

Business flow traced:
  [entry] -> [logic] -> [writes] -> [async/webhook] -> [final state]

Evidence timeline:
  [timestamp/event] — [what happened] — [source: log / DB / queue / webhook / code]

Root-cause findings:
  [finding] — Evidence: [Confirmed/Inferred] — Type: [rule / data / async / race / config / external]
  Failure boundary: [first place invariant breaks]
  Containment: [immediate step]
  Durable fix: [smallest safe fix]

Data or log checks used:
  [check] — [why it mattered]

Missing protections:
  [test / monitor / alert / invariant check]
```

## References

- `../../references/playbooks/bug-finding-playbook.md`
- `../../references/checklists/bug-finding-checklist.md`
- `../../references/checklists/root-cause-investigation-checklist.md`
- `../../references/playbooks/root-cause-investigation-playbook.md`
