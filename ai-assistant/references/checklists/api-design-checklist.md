# API Design Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What are the core resources, operations, or events in business terms?
- Is the contract consistent across naming, shapes, and required/optional fields?
- Are versioning and backward-compatibility expectations explicit?
- Are error responses structured, stable, and useful for clients?
- Are idempotency, retries, pagination, filtering, and async behavior designed intentionally?
- Are auth scopes, rate limits, and abuse-sensitive surfaces explicit?
- If webhooks or events exist, are replay, dedupe, ordering, and authenticity handled?
