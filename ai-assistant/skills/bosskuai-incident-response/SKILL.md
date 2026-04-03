---
name: bosskuai-incident-response
description: Use this for active incident triage, severity classification, escalation coordination, on-call communication, timeline reconstruction, and blameless postmortem facilitation.
---

# BosskuAI Incident Response

Use this skill when a production system is degraded, down, or behaving incorrectly at a level that requires coordinated response — not just individual debugging.

## How this differs from nearby skills

- **`bosskuai-bug-finding`**: traces technical root causes from code and evidence; load alongside this skill once the incident is stabilized and root cause investigation begins.
- **`bosskuai-cybersecurity-risk`**: assesses security threat surfaces; load alongside this skill when the incident has a suspected security or abuse vector.
- **`bosskuai-devops-iac`**: reviews operational infrastructure; load alongside when the incident points to infra, deployment, or config failures.
- **`bosskuai-business-logic-review`**: validates business rule correctness; load when the incident reveals a misencoded business rule rather than an infrastructure failure.

## Mindset

- During an active incident: stabilize first, understand second, improve third. Do not let investigation delay mitigation.
- Blame slows response and suppresses future reporting. Blameless postmortems improve systems, not individuals.
- An incident without a timeline is a guess. Build the timeline from evidence before drawing conclusions.
- Every incident is a gift: it reveals what monitoring, runbooks, and tests were missing.

## Severity classification

Use this as the default severity model. Adjust thresholds to your SLAs:

| Severity | Definition | Response target |
|----------|-----------|----------------|
| **SEV-1 (Critical)** | Complete service outage or data loss affecting production; payment or auth systems down | Immediate page; all-hands; 15-min update cadence |
| **SEV-2 (High)** | Significant user-facing degradation; major feature broken for >10% of users | Page on-call; 30-min update cadence |
| **SEV-3 (Medium)** | Partial or intermittent degradation; workaround exists; limited user impact | Notify team async; fix within business hours |
| **SEV-4 (Low)** | Minor issue or cosmetic failure; <1% user impact; no data risk | Track in backlog; fix in normal sprint |

## Incident response phases

### Phase 1 — Detect and classify (0–5 min)

1. **Acknowledge the signal** — Alert, customer report, or internal observation. Name it and own it.
2. **Classify severity** — Use the table above. When in doubt, start higher and downgrade.
3. **Open an incident channel** — Create a dedicated Slack channel, incident ticket, or war room. Name it with the date and brief description: `#inc-2026-04-02-payment-timeouts`.
4. **Assign roles**:
   - **Incident Commander (IC)**: coordinates response, owns communication. Does not debug.
   - **Technical Lead**: owns investigation and fix. Reports to IC.
   - **Comms Lead** (SEV-1/2): owns stakeholder and customer communication.

### Phase 2 — Stabilize (0–30 min for SEV-1/2)

5. **Mitigation first** — Can the impact be stopped now without understanding the root cause?
   - Rollback the last deployment
   - Disable the feature flag
   - Throttle the load or shed traffic
   - Failover to a redundant system
6. **Collect the initial snapshot** — What is broken? Who is affected? Since when? What changed recently?
7. **Communicate status** — Update stakeholders at the cadence for the severity level. Use a status template: "We are investigating [symptom] affecting [scope]. Mitigation in progress. Next update: [time]."

### Phase 3 — Investigate (parallel with stabilization)

8. **Build the evidence timeline** — Work backwards from the first symptom signal to the earliest detectable change:
   - Recent deployments (code, config, IaC)
   - Dependency or external API changes
   - Traffic or load anomalies
   - DB migration or data job completions
   - Alert firing sequence
9. **Identify the blast radius** — Who and what is affected? Is the scope growing, stable, or shrinking?
10. **Delegate technical investigation** to `bosskuai-bug-finding` deep investigation mode once the incident is stable.

### Phase 4 — Resolve and verify (when fix is ready)

11. **Apply the fix with a plan** — State the expected change and how it will be verified before it is applied.
12. **Verify resolution** — Confirm metrics return to baseline. Do not declare resolved based on code deploy alone.
13. **Communicate resolution** — "Incident resolved at [time]. Root cause: [brief]. Fix applied: [brief]. Post-incident review: [date]."
14. **Reduce severity or close** — Downgrade to SEV-4 or close the incident channel.

### Phase 5 — Postmortem (within 48–72h for SEV-1/2)

15. **Reconstruct the timeline** — Use logs, alerts, deploy history, and slack timestamps to build a factual event sequence. Avoid narratives before evidence.

16. **Use the 5-Whys** — Ask "why" iteratively to move from symptom to contributing factor:
    - Why did users see errors? → API returned 500s
    - Why did API return 500s? → DB connection pool exhausted
    - Why was pool exhausted? → slow queries from a missing index added in the migration
    - Why was the missing index not caught? → no query plan check in the migration review
    - Why was there no query plan check? → no checklist item for this

17. **Identify contributing factors, not villains** — Every incident has multiple contributing factors: monitoring gaps, missing runbooks, process gaps, and code defects — not a single person's mistake.

18. **Write the postmortem document** with these sections:
    - **Summary**: one paragraph of what happened, when, and impact
    - **Timeline**: factual event sequence with timestamps and sources
    - **Root cause(s)**: technical and contributing factors, from the 5-Whys chain
    - **Impact**: scope of user and business impact (duration, % affected, revenue if known)
    - **What went well**: things that limited impact or sped recovery
    - **What did not go well**: gaps in detection, response, or tooling
    - **Action items**: each with an owner, priority, and due date

19. **Promote durable learnings** — Each action item should land in a checklist, runbook, alert, or code fix — not just a postmortem document that is never read again.

## Escalation paths

Define these for your org before the next incident:

- Who is the primary on-call for each service tier?
- Who is notified for SEV-1 (leadership, external comms, legal if data breach)?
- What is the external status page update SLA?
- What is the customer communication template for SEV-1/2?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not spend more than 5 minutes debating severity during an active incident — pick the higher severity and revisit.
- Do not assign blame in a postmortem — name systems and processes, not individuals.
- Do not wait for a full root cause before mitigating impact — stabilize first, investigate second.
- Do not close an incident without a verified resolution — a deployed fix that hasn't been confirmed working is not a resolution.
- Do not skip the postmortem for SEV-1 and SEV-2 incidents — undocumented incidents repeat.

## Output format

**Active incident:**
```text
Incident: [brief name]
Severity: [SEV-1/2/3/4] — [rationale]
Scope: [who/what is affected]
Status: [Detecting / Stabilizing / Investigating / Resolved]

Roles:
  IC: [name or TBD]
  Tech Lead: [name or TBD]

Current mitigation:
  [action taken or in progress]

Evidence snapshot:
  Last deployment: [when / what]
  Anomaly first seen: [when / source]
  Blast radius: [scope]

Next update: [time]
```

**Postmortem:**
```text
Postmortem: [incident name] — [date]
Severity: [SEV-x]
Duration: [start] → [end] ([total minutes])
Impact: [user/business scope]

Timeline:
  [timestamp] — [event] — [source]

5-Whys:
  [why chain from symptom to contributing factors]

Root cause(s): [technical + contributing]

What went well: [list]
What did not go well: [list]

Action items:
  [action] — [owner] — [priority: P1/P2/P3] — [due date]
```

## References

- `../../references/playbooks/bug-finding-playbook.md`
- `../../references/checklists/root-cause-investigation-checklist.md`
- `../../references/checklists/devops-iac-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
