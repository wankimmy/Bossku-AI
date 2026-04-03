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
   - A06 Vulnerable Dependencies — outdated packages with known CVEs? Is there a SBOM? Are transitive dependencies pinned?
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

## Supply chain security

Use this section when reviewing dependency management, open-source usage, CI/CD supply chain, or third-party code ingestion.

### SBOM (Software Bill of Materials)
- A SBOM is a machine-readable inventory of all direct and transitive dependencies and their versions.
- Generate a SBOM as part of CI and store it with each release artifact (SPDX or CycloneDX format).
- Without a SBOM, CVE scanning is guesswork — scanners need a precise dependency manifest.
- Treat SBOM generation as a build artifact, not a one-off audit task.

### CVE scanning
- Run CVE scanning (e.g. `trivy`, `grype`, `snyk`, `dependabot`) on every PR and every base image build.
- Block merges on Critical/High CVEs with known exploits; warn on Medium. Do not silently suppress scanner output.
- Track CVE age: a High CVE that has been open for 30+ days without a mitigation plan is a compliance and liability risk.
- For container images: scan both the application layer and the base image. Base image CVEs are often the most numerous.

### Dependency pinning strategy
- Pin direct dependencies to exact versions in lock files (`package-lock.json`, `poetry.lock`, `go.sum`). Do not commit only a ranges-based manifest.
- Pin transitive dependencies wherever the toolchain supports it.
- For CI/CD actions and IaC modules: pin to a commit SHA, not a mutable tag (e.g. `actions/checkout@v3` is mutable; `actions/checkout@abc1234` is not).
- Review dependency updates via automated PRs (Dependabot, Renovate) rather than manual periodic bumps — automation ensures freshness without losing auditability.

### Open-source license compliance
- Before adding a dependency, check its license against your project's allowed license list.
- Risky licenses for commercial products: GPL/AGPL (copyleft, may require source disclosure), SSPL (MongoDB-style, very broad copyleft), or "no license" (legally all rights reserved).
- Safe licenses for commercial use: MIT, Apache 2.0, BSD, ISC.
- Automate license scanning (e.g. `license-checker`, `fossa`, `snyk license`) in CI to catch new dependencies with incompatible licenses before merge.
- Maintain a `THIRD_PARTY_LICENSES` or `NOTICE` file if your license requires it.

### Third-party code ingestion risk
- Code copied from LLM outputs or Stack Overflow may carry GPL snippets, incompatible licenses, or known-vulnerable patterns — treat it as untrusted until reviewed.
- Do not ingest third-party IaC modules or GitHub Actions from unvetted sources without reviewing their content.
- Pin third-party container base images to digest hashes in production builds, not floating tags.

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
