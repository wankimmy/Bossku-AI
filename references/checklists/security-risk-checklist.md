# Security Risk Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What data is sensitive?
- What are the trust boundaries?
- Who can trigger this action?
- What happens if a user is malicious, careless, or compromised?
- Are auth, authorization, and tenant/org boundaries explicit?
- Are user inputs validated and dangerous outputs sanitized where needed?
- Are secrets handled outside source code and protected from logs or error output?
- Are injection paths, unsafe file access, or permission escalation risks present?
- Are rate limiting, abuse controls, and safe failure modes appropriate?
- Could this flow be abused for fraud, spam, scraping, or escalation?
- Are auditability and incident response considerations present?
- Are there privacy, compliance, or retention obligations?
