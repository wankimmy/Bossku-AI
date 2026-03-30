---
name: bosskuai-root-cause-investigation
description: Use this for comprehensive bug investigation using business-logic tracing plus runtime evidence such as database state, logs, jobs, queues, webhooks, and external side effects to locate the true failure boundary.
---

# BosskuAI Root Cause Investigation

Use this skill when the bug cannot be explained from code alone and you need to correlate **business logic**, **runtime evidence**, and **system state**.

## How this differs from nearby skills

- **`bosskuai-bug-finding`**: focuses on tracing likely defects in code paths; this skill adds logs, database state, queues, jobs, webhooks, and operational evidence to confirm the true root cause.
- **`bosskuai-business-logic-review`**: checks whether workflow rules are encoded correctly in theory; this skill investigates whether those rules failed in practice for a concrete incident.
- **`bosskuai-codebase-analysis`**: maps execution paths before judgment; this skill uses that map to correlate code with real-world evidence.
- **`bosskuai-cybersecurity-risk`**: focuses on security incidents or abuse; load alongside when the investigation touches auth, fraud, secrets, or sensitive data.

## Mindset

- The symptom is rarely the root cause.
- Production truth often lives across code, DB rows, logs, jobs, caches, and external systems together.
- Business-logic bugs are often silent data/state bugs before they are visible UI bugs.
- Read-only evidence collection is the default. Be careful with production data, secrets, and PII.

## Evidence surfaces to inspect

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

## Investigation workflow

1. **Define the incident clearly**
   - expected behavior
   - actual behavior
   - impacted entity or user segment
   - timeframe
   - environment

2. **Trace the business flow**
   - entry point
   - validations
   - business rules
   - data writes
   - async jobs / webhooks
   - downstream side effects

3. **Build the evidence timeline**
   - user action or triggering event
   - request log or job start
   - DB state changes
   - external callbacks or failures
   - final wrong state or user-visible symptom

4. **Inspect data state directly**
   - compare expected vs actual row state
   - check status fields, timestamps, foreign keys, retry counts, idempotency keys, soft deletes, and audit history
   - look for partial writes, stale rows, missing transitions, or duplicate records

5. **Inspect logs and operational traces**
   - correlate by request ID, entity ID, job ID, idempotency key, payment reference, or user ID
   - find the earliest divergence between expected and observed behavior
   - pay attention to silent retries, swallowed exceptions, timeout fallbacks, and background workers

6. **Test the business invariant**
   - what rule should have prevented this?
   - was the rule missing, bypassed, racing, or operating on stale data?

7. **Classify the root cause**
   - business rule encoded incorrectly
   - missing validation or authorization
   - race condition / transaction gap
   - retry / idempotency failure
   - stale cache / stale read
   - webhook / async handoff failure
   - bad migration / bad data repair
   - config or environment drift

8. **Recommend the smallest safe fix**
   - immediate containment
   - durable code or schema fix
   - missing test / monitor / alert
   - data repair steps if required

## Guardrails

- Do not mutate production data or replay jobs unless explicitly asked and it is safe to do so.
- Do not assume the latest error log line is the root cause; build the full timeline first.
- Do not inspect sensitive production data casually; minimize exposure and summarize safely.
- Do not stop at “code looks fine” when DB state or logs contradict that conclusion.

## Output format

```text
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

- `../../references/checklists/root-cause-investigation-checklist.md`
- `../../references/playbooks/root-cause-investigation-playbook.md`
