---
name: bosskuai-cybersecurity-risk
description: Use this for cybersecurity review, privacy, abuse-case analysis, auth and authorization concerns, trust boundaries, fraud risk, and operational risk evaluation.
---

# BosskuAI Cybersecurity and Risk

Use this skill when the task involves **security, privacy, abuse, or operational risk** — either reviewing new code/designs or auditing an existing system.

## How this differs from nearby skills

- **`bosskuai-rigorous-code-review`**: reviews code quality and correctness; load alongside this skill when a diff touches auth, payments, PII, or external APIs.
- **`bosskuai-agent-security-hardening`**: secures the AI-agent harness itself (instructions, MCP, memory, hooks); load when the concern is the agent workspace, not the application.
- **`bosskuai-business-logic-review`**: catches wrong rules; load alongside when authorization or approval logic may be exploitable.

## Mindset

- Every system has an attacker model — if you haven't defined it, an attacker already has.
- Most security failures are not exotic: they are missing validation, wrong trust assumptions, or hardcoded secrets.
- Defense in depth: each layer should reduce risk even if other layers fail.
- Privacy is a security concern — data that exists can be breached; data that isn't collected cannot.

## STRIDE threat categories

Apply STRIDE systematically for each trust boundary and sensitive flow:

| Threat | Question to ask |
|--------|----------------|
| **Spoofing** | Can an attacker impersonate a user, service, or system? |
| **Tampering** | Can data be modified in transit, at rest, or in memory without detection? |
| **Repudiation** | Can a user deny performing an action? Are audit logs complete and tamper-evident? |
| **Information Disclosure** | Can sensitive data (PII, credentials, business data) be read without authorization? |
| **Denial of Service** | Can the system be made unavailable? Are rate limits, timeouts, and circuit breakers in place? |
| **Elevation of Privilege** | Can a user gain more access than intended? |

## Workflow

1. **Identify sensitive assets** — What data, keys, capabilities, or services need protecting? Classify: credentials, PII, financial data, business-sensitive, operational data.

2. **Map trust boundaries** — Where does data cross from one trust zone to another (internet → app, app → DB, service → service, user → admin)? Every boundary is a potential attack surface.

3. **Apply STRIDE** — Work through each threat for each sensitive flow and trust boundary.

4. **Check the OWASP Top 10 baseline** (for web/API surfaces):
   - A01 Broken Access Control — can users reach resources they should not?
   - A02 Cryptographic Failures — weak algorithms, improper key management, unencrypted sensitive data?
   - A03 Injection — SQL, NoSQL, shell, LDAP, template, path traversal?
   - A04 Insecure Design — missing rate limits, trust assumptions baked in, no abuse cases considered?
   - A05 Security Misconfiguration — default creds, open cloud storage, verbose error messages?
   - A06 Vulnerable Dependencies — outdated packages with known CVEs?
   - A07 Authentication Failures — broken session management, weak credentials, no MFA where needed?
   - A08 Data Integrity Failures — unsigned updates, deserializing untrusted data?
   - A09 Logging and Monitoring Failures — no audit trail for sensitive operations, no alerting on anomalies?
   - A10 SSRF — can the server be made to fetch attacker-controlled URLs?

5. **Check auth and authorization specifically**:
   - Authentication: how is identity established? Is it verifiable and unforgeable?
   - Authorization: is access checked at every layer, or only at the entry point?
   - Privilege escalation paths: can a low-privilege action chain into a high-privilege one?
   - Session management: session fixation, rotation, expiry, invalidation on logout.

6. **Check secrets and data handling**:
   - No secrets in source code, logs, URLs, error messages, or client-side responses.
   - Secrets injected at runtime via env vars or vault, not baked in config files.
   - PII minimization: do you need to store this? If yes, is it encrypted at rest?

7. **Review auditability and recovery**:
   - Are sensitive operations (permission changes, financial transactions, data exports) logged with actor, timestamp, and resource?
   - Is the audit log write-only from the application's perspective?
   - What is the incident response and rollback path if a vulnerability is exploited?

8. **Separate confirmed issues from inferred risks** — State evidence for confirmed findings; label inferred risks clearly.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not recommend security theater (adding complexity that doesn't reduce actual risk).
- Do not list every possible vulnerability — focus on what is plausible given the actual threat model.
- Do not assume external APIs, webhooks, or user-supplied data are safe.
- Label severity: Critical (immediate exploitation risk) / High (significant risk) / Medium (conditional risk) / Low (defense in depth).

## Output format

```
Threat model: [assets / trust boundaries / attacker profile]
STRIDE findings:
  [S/T/R/I/D/E] — [flow affected] — [risk] — [severity] — [mitigation]
OWASP gaps (if applicable):
  [category] — [finding] — [fix]
Auth and authorization findings: [if applicable]
Secrets and data handling findings: [if applicable]
Missing controls: [what is not in place that should be]
Recommended mitigations (ordered by severity): [list]
Confirmed vs inferred: [label each]
```

## References

- `../../references/playbooks/security-review-playbook.md`
- `../../references/checklists/security-risk-checklist.md`
- `../../references/checklists/agent-security-hardening-checklist.md`
- `../../references/pitfalls/security-pitfalls.md`
