# API Design Checklist

- What are the core resources, operations, or events in business terms?
- Is the contract consistent across naming, shapes, and required/optional fields?
- Are versioning and backward-compatibility expectations explicit?
- Are error responses structured, stable, and useful for clients?
- Are idempotency, retries, pagination, filtering, and async behavior designed intentionally?
- Are auth scopes, rate limits, and abuse-sensitive surfaces explicit?
- If webhooks or events exist, are replay, dedupe, ordering, and authenticity handled?
