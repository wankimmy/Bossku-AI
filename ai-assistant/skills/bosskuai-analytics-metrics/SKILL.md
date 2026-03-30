---
name: bosskuai-analytics-metrics
description: Use this for instrumentation strategy, event design, funnels, experimentation, north-star metrics, KPI definitions, attribution logic, and making sure product decisions can be measured reliably.
---

# BosskuAI Analytics / Metrics

Use this skill when the main question is **what to measure, how to instrument it, and whether the numbers will support good decisions**.

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: defines product bets and hypotheses; this skill defines how those bets will be measured.
- **`bosskuai-marketing-growth`**: optimizes acquisition and distribution; this skill designs measurement across product and growth surfaces.
- **`bosskuai-engineering-delivery`**: implements tracking; this skill decides the event model, funnel definitions, and metric semantics first.

## Mindset

- If a metric can’t drive a decision, it is probably vanity.
- Event definitions are contracts; sloppy naming creates years of bad dashboards.
- Funnel stages must map to real user behavior, not wishful narratives.
- Instrumentation should be privacy-conscious and operationally debuggable.

## Measurement lenses

**Outcome alignment**
- What decision or hypothesis will this metric support?
- Is there a north-star or guardrail metric?
- What would success or failure look like?

**Event model**
- Are events named for user actions or system facts clearly?
- Is event grain explicit?
- Are properties stable, typed, and necessary?

**Funnels and experiments**
- Are stages mutually understandable and time-bounded?
- Can drop-off be explained by available events?
- Are experiment variants and exposure events explicit?

**Attribution and identity**
- How are users, sessions, orgs, devices, and anonymous states linked?
- Where can attribution be lost or duplicated?
- Are cross-platform identities reconciled clearly?

**Data quality**
- Are dedupe, sampling, clock skew, and late events handled?
- Is there a way to verify the numbers against source actions?
- What monitoring catches tracking drift?

**Privacy and retention**
- Is sensitive data unnecessarily captured?
- Are consent and retention requirements reflected in instrumentation?
- Can deletion or subject-access obligations be honored?

## Workflow

1. **Read the current measurement surface** — event docs, analytics code, dashboards, experiment tooling, and product specs.
2. **Define the decision question first** — what decision needs support and what metric would change behavior?
3. **Map the event model** — user actions, system facts, experiment exposures, identity joins, and funnel stages.
4. **Review data quality and attribution risks** — duplicates, missing events, late events, cross-device issues, and naming drift.
5. **Review privacy constraints** — PII, consent, retention, and deletion expectations.
6. **Recommend the smallest instrumentation slice** — add or refine only what supports a real decision.
7. **Name the validation plan** — how the team will verify the instrumentation is correct after shipping.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not add events with no clear decision owner.
- Do not stuff unbounded blobs or sensitive data into event properties.
- Do not define funnels without precise event semantics and time windows.
- Do not treat dashboard completeness as proof that the data is correct.

## Output format

```text
Measurement goal:
  Decision to support: [question]
  Primary metric(s): [list]
  Guardrail metric(s): [list]

Event model:
  Core events: [list]
  Required properties: [list]
  Funnel or experiment mapping: [summary]

Risks:
  Attribution/identity: [findings]
  Data quality: [findings]
  Privacy/retention: [findings]

Recommended instrumentation slice:
  [change] — [why] — [how to validate]
```

## References

- `../../references/checklists/analytics-metrics-checklist.md`
- `../../references/playbooks/analytics-metrics-playbook.md`
