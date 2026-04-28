# bosskuai-cybersecurity-risk Full Playbook

Original detailed operating notes moved out of SKILL.md to reduce prompt bloat.

---

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

## Application security addendum

For app/API/payment/webhook reviews, check:

- webhook signature verification and replay protection,
- idempotency for external events,
- tenant isolation at query and policy layers,
- secrets in env/config only,
- PII minimization and retention,
- dependency CVE scanning,
- incident rollback and recovery plan.

---

## Worked threat models

Each example is a real cofounder-MVP shape. The wrong-shaped review just lists OWASP categories. The right-shaped review names the specific assets, traces specific flows, and proposes mitigations the founder can actually ship this week.

### Example 1 — Stripe webhook handler

**System**: Laravel app, `POST /webhooks/stripe` updates order state.

**Wrong-shaped review:**

> Per OWASP A01 and A05, ensure proper authentication and configuration. Validate input.

(Useless. No specific finding, no fix.)

**Right-shaped review:**

```text
Asset: order state, payment confirmation, refund triggers
Trust boundary: public internet -> webhook handler

Findings:
  S (Spoofing) — Anyone can POST a fake event.
    Severity: Critical
    Evidence: route handler reads body without verifying Stripe-Signature header
    Fix: Webhook::constructEvent($body, $sig, config('services.stripe.webhook_secret'))

  T (Tampering / Replay) — Same event can be replayed to trigger same state change twice
    Severity: High
    Evidence: no persistence of external event ID
    Fix: persist event.id in webhook_events with UNIQUE constraint; return 200 if already seen

  D (Denial of Service) — Heavy synchronous work in handler can be used to exhaust workers
    Severity: Medium
    Evidence: handler runs DB writes + email + analytics inline
    Fix: queue all side effects; handler does verify+persist+dispatch only

Confirmed: Spoofing (route reviewed)
Inferred: Replay (no DB inspection done — high probability based on code)

Smallest fix sequence (P0/P1):
  1. Add Webhook::constructEvent + 400 on bad signature.
  2. Add webhook_events table, unique on (provider, external_id), insert before processing.
  3. Move side effects to ProcessStripeEvent job dispatched from handler.

Verification:
  - Send valid event twice — second call returns 200 with no duplicate side effects.
  - Send event with wrong signature — returns 400, no row inserted.
  - Send replayed event from 1h ago — returns 200, no duplicate processing.
```

### Example 2 — multi-tenant data leak risk

**System**: Laravel + MariaDB SaaS where each tenant has many users; users belong to one tenant.

**Wrong-shaped review:**

> Ensure broken access control (A01) is mitigated through proper authorization.

**Right-shaped review:**

```text
Asset: tenant data isolation
Trust boundary: authenticated user (tenant A) -> any data row in DB

Threat: a tenant A user finds a tenant B record via:
  - direct ID guessing in URL (/orders/12345)
  - search endpoints that bypass tenant scope
  - join tables (OrderItem, OrderEvent) where tenant_id is denormalized away
  - admin/internal endpoints with weak guards
  - exported reports / CSV downloads that don't re-check scope

Findings:
  E (Elevation / Broken Access Control) — direct ID guessing in URLs
    Severity: Critical
    Evidence: OrderController@show uses Order::findOrFail($id) without policy check
    Fix: $this->authorize('view', $order) plus tenant_id check in OrderPolicy

  I (Information Disclosure) — search bypasses scope
    Severity: Critical
    Evidence: Item::where('sku', $sku)->first() in search controller, no global scope on Item
    Fix: addGlobalScope on Item that filters by current tenant; remove the bypass

  I (Information Disclosure) — API Resource leaks internal fields
    Severity: High
    Evidence: OrderResource uses parent::toArray() which dumps every column
    Fix: explicit allow-list in toArray()

Smallest fix sequence:
  1. Apply BelongsToTenant trait to every tenant-owned model (including join tables).
  2. Add policy + authorize() to every controller method that mutates or reads tenant data.
  3. Convert all *Resource::toArray() to allow-list form.

Verification (regression test):
  - Boot two tenants A and B with overlapping data.
  - Make every API call as user A; assert ONLY tenant A's rows in every response.
  - Make every API call with deliberately-guessed tenant B IDs; assert 403/404.
```

(See `bosskuai-laravel-development-playbook.md` §7 for the global scope code pattern.)

### Example 3 — secrets in version control

**System**: Nuxt app, just shipped first deploy, CI/CD via GitHub Actions.

**Wrong-shaped review:**

> Don't commit secrets. Use environment variables.

**Right-shaped review:**

```text
Asset: API keys, database credentials, signing secrets
Trust boundary: public source repo / build logs / Docker image layers

Threat surfaces (in order of how often they bite):
  1. .env committed to git (most common)
  2. .env.example with real-looking placeholder values that are real
  3. Hard-coded keys in Nuxt runtime config
  4. Secrets logged in CI build output
  5. Secrets baked into Docker image layers via ARG or COPY .env
  6. Secrets in client bundle (NUXT_PUBLIC_* leak to browser)

Findings:
  I (Information Disclosure) — Stripe key leaked in client bundle
    Severity: Critical
    Evidence: nuxt.config.ts uses runtimeConfig.public.stripeKey = process.env.STRIPE_KEY
    Fix: Use server-only config (runtimeConfig.stripeKey, no .public). Server routes call Stripe; client never has the key.

  I (Information Disclosure) — .env in git history (even if currently removed)
    Severity: Critical (if it ever contained prod creds)
    Evidence: git log -- .env shows commits
    Fix: ROTATE the secret first (assume compromised). Then rewrite history with git-filter-repo OR — if history is short — squash and force-push. Add .env to .gitignore. Set up gitleaks pre-commit hook.

  I (Information Disclosure) — secrets in Docker image
    Severity: High
    Evidence: Dockerfile has COPY . . which includes .env
    Fix: .dockerignore must exclude .env, .env.*, .git, secrets/. Use BuildKit secrets for build-time creds.

Verification:
  - git log -p -- .env returns nothing (or all values verified rotated).
  - gitleaks detect on the repo returns clean.
  - View-source on the deployed Nuxt page; grep for any key prefix (sk_, AKIA, etc.) returns nothing.
  - docker history <image> shows no secret-like ENV/ARG values.
  - CI build logs grep for known key prefixes — no matches.
```

## Risk vs theater — what to skip in MVP-stage security

A founder-stage cofounder must distinguish real risk reduction from security theater. Skip these in MVP unless evidence demands them:

| Often-recommended | When it's actual risk reduction | When it's theater at MVP |
|---|---|---|
| WAF (Cloudflare/AWS WAF) | After first abuse incident, or PCI scope | Day 1, with no traffic to abuse |
| Hardware security keys for team | Once team > 3 or post-revenue | Solo founder pre-revenue |
| SOC 2 audit | When asked by an enterprise prospect | Before anyone has asked |
| Penetration test | Before B2B deal closes / funding due-diligence | Before having paying users |
| Dedicated SIEM | Once compliance scope demands it | First 6 months of any startup |
| End-to-end encryption | When users' data leaving server is the actual threat | When the threat is just "secure communication" — TLS already does that |
| Bug bounty program | After internal review baseline + sufficient surface | Before fixing the obvious P0/P1s |

Real risk reduction at MVP, in priority order:

1. **TLS everywhere + correct cookie flags** (Secure, HttpOnly, SameSite=Lax|Strict).
2. **Webhook signature + replay protection** (real money loss otherwise).
3. **Tenant isolation tests** in CI (the other real-money loss).
4. **Secret rotation runbook** (you will leak one within 12 months — preparation matters more than prevention).
5. **MFA on every team member's GitHub, hosting provider, DNS, email, Stripe, Postgres provider.**
6. **Backups tested by being restored** (untested backup = no backup).
7. **CVE scanning on every PR** (free with GitHub Dependabot / Trivy).
8. **Rate limiting on auth endpoints** (login, password reset, signup, OTP).
9. **Audit log for sensitive actions** (permission changes, refunds, user impersonation).
10. **A `SECURITY.md` with a real disclosure email** (someone will find something; make it easy to tell you).

## Privacy / data minimization decisions

Founders default to "collect everything just in case." That's the wrong default. Right-shape:

- **Don't collect** what you can't articulate a use for in the next 90 days.
- **If you must collect**, define retention up front (e.g. analytics events: 90d, customer support transcripts: 1y, financial records: 7y per law).
- **Pseudonymize aggressively**: hash IPs at ingest, separate user ID from PII via a join table you can drop.
- **Right-to-deletion is a build cost**: every PII column you add increases the cost of GDPR/CCPA/PDPA deletion requests. Cheaper to not collect.
- **Logs are PII surface area**: do not log full request bodies, full headers, or full response bodies in production. Redact tokens and passwords explicitly.

## Auth-specific anti-patterns

| Pattern | Why bad | Fix |
|---|---|---|
| Self-rolled JWT signing | One bug = full compromise | Use the framework's session driver or a maintained library (Sanctum, Passport, NextAuth) |
| Storing passwords with custom hashing | One bug = customer compromise | Argon2 or bcrypt via framework default; never SHA-anything |
| 6-digit OTP without rate limit + lockout | 1M tries breaks it | Lockout after 5 failures within 15min, plus per-account exponential backoff |
| Password reset link with no expiry | Inbox compromise → permanent compromise | 1-hour expiry; single-use; bind to the email account that requested it |
| Long-lived API tokens | Loss = permanent breach until manual rotation | Short-lived access + refresh, OR scoped tokens with documented revocation |
| Allow `email` change without confirmation | Account takeover via support social engineering | Require re-auth + email confirmation to old AND new addresses |

## Incident response — what to have before you need it

A founder will face a security incident at some point. Have these BEFORE the incident:

1. **One page runbook** with:
   - Who gets called first (and second).
   - How to revoke all API keys at every provider (15-minute drill).
   - How to rotate database credentials.
   - How to invalidate all user sessions.
   - Where the audit logs live.
2. **`security@yourcompany.com`** monitored by a human, listed in DNS + on the website + in `SECURITY.md`.
3. **Written disclosure policy**: "we acknowledge in 48h, fix critical in 7d" — not because you have to, because the lack of one slows you down at the worst time.
4. **Customer communication template** for "we had an incident, here's what happened, here's what we did" — drafted while calm, not at 2 AM.
5. **Rollback procedures** for the last 7 deploys, tested by drill (not just documented).
