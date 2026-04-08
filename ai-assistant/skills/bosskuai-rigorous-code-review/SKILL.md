---
name: bosskuai-rigorous-code-review
description: Skeptical expert code review that maps changes to repo structure and infrastructure, applies strict engineering standards, and prefers minimal fixes. Use for PR or pre-merge review, harsh or adversarial review requests, challenging an implementation, or when the user wants strict best practices without unnecessary refactors.
---

# BosskuAI Rigorous Code Review

Use this skill when the goal is to **review** code (diffs, PRs, new modules, or proposed patches) with an **expert, skeptical** lens — not to rewrite the system by default.

## How this differs from nearby skills

- **`bosskuai-bug-finding`**: traces failure paths and defects; load both when review should emphasize *what breaks*.
- **`bosskuai-coding-best-practices`**: quality and conventions when *writing* or refactoring; this skill stresses *challenge and gatekeeping* on a proposed change.
- **`bosskuai-code-revamp`**: use when a **large** structural change is explicitly appropriate; this skill **flags** that need but does **not** expand scope unless evidence demands it.
- **`bosskuai-codebase-analysis`**: orientation and structure mapping; skim or use first if the review target sits in an unfamiliar area.
- **`bosskuai-cybersecurity-risk`**: deep threat modeling; load alongside this skill when the diff touches auth, payments, PII, or external APIs.

## Mindset

- Assume the change might be wrong until justified by code, tests, contracts, and operational context.
- Challenge: necessity, boundaries, error handling, security, observability, backwards compatibility, and rollout.
- Prefer **strict** best practices in the form of **small, local improvements** that match existing project patterns.
- **Major or cross-cutting changes** are reserved for clear triggers (repeated duplication across layers, wrong abstraction boundary, systemic security gap, unmaintainable coupling). State **why** a small fix is insufficient before recommending them.
- Multi-perspective review: run separate passes for security, performance, correctness, and convention compliance rather than a single unstructured read.
- Confidence scoring: assign each finding a 0–100 confidence score. Only block on findings above 80% confidence to reduce false positives and review noise.

## Severity scale

| Label | Meaning | Action |
|-------|---------|--------|
| **P0 — Block** | Correctness failure, data loss, security vulnerability, contract break | Must fix before merge |
| **P1 — Must-fix** | High bug risk, auth gap, missing validation at a boundary, broken test | Fix in this PR |
| **P2 — Should-fix** | Maintainability debt, weak error handling, unclear contract | Fix soon or in follow-up |
| **P3 — Nit** | Style only, naming, minor readability | Optional; only flag when it violates project norms |

## Workflow

1. **Map context** — Identify how the change fits: directories, modules, public APIs, config, build/deploy (CI, env, migrations, feature flags), and runtime dependencies. Use `project-understanding` / `codebase-analysis` if the layout is unclear.

   **Graph-aware acceleration (when `code-review-graph` MCP tools are available)**:
   - Start with `get_minimal_context_tool(task="review changes", base=<base>)` to get graph stats, risk, and suggested next tools before broad file scanning.
   - If the graph is missing or stale, run `build_or_update_graph_tool(postprocess="minimal")`; use `full_rebuild=True` only after branch switches, major refactors, or clearly corrupt graph state.
   - Use `detect_changes_tool(base=<base>, detail_level="minimal")` as the first review pass. Escalate to `detail_level="standard"` or `get_review_context_tool(include_source=True, max_depth=2)` only for high-risk areas or unclear findings.
   - For high-risk functions, query `query_graph_tool(pattern="callers_of", target=<symbol>)`, `query_graph_tool(pattern="tests_for", target=<symbol>)`, and `get_affected_flows_tool(base=<base>)` before deciding whether the blast radius is covered.
   - Treat graph output as a triage map, not proof. Confirm every blocking finding by reading the diff, relevant source, and tests directly. If graph tools are unavailable, proceed with `git diff`, `rg`, call-site reads, and test inspection.

2. **Read evidence** — Inspect the diff **and** call sites, tests, types/schemas, and infra touchpoints (Docker, k8s, serverless, pipelines) affected. Do not review the diff in isolation.

3. **Stress-test by category** — Work through each category below. Skip only if clearly irrelevant:

   **Correctness**
   - Edge cases, boundary values, null/undefined/empty states
   - Concurrency: race conditions, shared mutable state, ordering assumptions
   - Idempotency and partial failure recovery
   - Retries and duplicate execution risk

   **Security**
   - Input validation and sanitization at every trust boundary
   - Injection risks: SQL, shell, LDAP, template, path traversal
   - Authentication and authorization: who can call this? are checks enforced?
   - Secrets and credentials: hardcoded, logged, leaked in errors or responses
   - Cryptography: weak algorithms, insecure random, improper key management
   - CSRF, XSS, open redirects (web surfaces)
   - Rate limiting and abuse surface
   - Audit trail: are sensitive operations logged without leaking sensitive data?

   **Performance & resources**
   - N+1 queries or unbounded DB/API calls in loops
   - Memory allocations that grow without bounds
   - Blocking I/O on hot paths
   - Missing indexes, full-table scans, or lock contention
   - Caching assumptions that don't hold under load

   **Test coverage**
   - Does the change include tests for new behavior?
   - Are failure paths, edge cases, and error conditions tested?
   - Does the test setup match what production actually does (no mocked-away behavior that masks real bugs)?
   - Are existing tests still meaningful, or do they need updating?

   **API contracts & backwards compatibility**
   - Does this break any existing callers (internal or external)?
   - Are schema changes additive-only or do they remove/rename fields?
   - Is versioning handled if the contract is public?
   - Are deprecations communicated?

   **Operational concerns**
   - Logging: is it useful without being noisy or leaking sensitive data?
   - Metrics and alerting surface: can on-call see if this breaks?
   - Migration safety: DB migrations backwards-compatible? Rollback path clear?
   - Feature flag or gradual rollout if risk is high?

   **AI/LLM-generated code markers** (flag if suspicious)
   - Hallucinated library methods or non-existent APIs
   - Overly generic error handling that swallows failures silently
   - Missing context-specific validation that a human would naturally add
   - Boilerplate that doesn't match the project's actual patterns
   - Inconsistent naming or style breaks within the same diff

4. **Classify findings** — Assign each finding a severity label (P0–P3) and a confidence score (0–100). Group by category if there are many. Only post findings with confidence ≥ 80% by default.

   **Optional MCP integration**: If CodeRabbit CLI is available, run `coderabbit:review` for AST-level static analysis (40+ analyzers). Merge its findings into your review with the same severity/confidence framework.

5. **Recommend minimally** — For each issue, propose the **smallest** change that resolves it. If only a large refactor helps, label it **scope escalation**, give one concise rationale, and suggest **`bosskuai-code-revamp`** or a tracked follow-up — not a drive-by rewrite.

6. **Apply the Review Definition of Done** — Before declaring the review complete, confirm:
   - [ ] All diff files read — not skimmed, read
   - [ ] All 7 stress-test categories worked through (or explicitly skipped with reason)
   - [ ] Every P0/P1 finding has a specific file:line reference and a concrete fix
   - [ ] Verdict is defensible: if asked "why did you approve this?", the answer is in the review
   - [ ] Non-goals stated: what was NOT reviewed and why (scope, familiarity, time constraints)
   - [ ] No finding was added from assumption — every finding traces to specific evidence in the diff

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not request changes without a specific file:line reference and a concrete minimal fix — vague findings are not actionable.
- Do not expand scope to a full refactor unless the review evidence demands it — flag as scope escalation instead.
- Do not review the diff in isolation — read call sites, tests, and infra touchpoints affected.

## Output format

```
Verdict: [Approve with nits | Request changes (P1–P2) | Block (P0)]

Structure & infra fit: [one sentence on how the change lands in the repo/deploy path]

Findings:
  [P0] <file:line or symbol> — <evidence> → <minimal fix>
  [P1] ...
  [P2] ...
  [P3] ...

Non-goals: [what you are NOT refactoring and why]
```

- Keep findings ordered by severity descending.
- If a category has no findings, omit it — do not write "no issues found" for every category.
- If the diff is clean, say so in one line and list any nits inline.

## References

- `../../references/checklists/code-review-checklist.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/verification-checklist.md`
- `../../references/checklists/bug-finding-checklist.md`
- `../../references/checklists/architecture-review-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
- `../../references/playbooks/graph-aware-code-review-playbook.md`
