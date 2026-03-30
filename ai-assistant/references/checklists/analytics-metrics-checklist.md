# Analytics / Metrics Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What decision or hypothesis will this metric or instrumentation support?
- Are the core events and properties named clearly and defined at the right grain?
- Are funnels, guardrails, and experiment exposure events explicit?
- How are user, org, session, and anonymous identities joined or separated?
- What can cause duplicates, missing events, late events, or attribution drift?
- Are privacy, consent, retention, and deletion obligations respected in the tracking model?
- How will the team validate that the numbers match real behavior after shipping?
