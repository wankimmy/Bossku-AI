---
name: bosskuai-browser-automation
description: Use this skill for browser-level testing, UI verification, automated user flow testing, visual regression, and scraping JavaScript-rendered pages. Covers QA smoke tests through full user journey automation.
---

# BosskuAI Browser Automation

## When to use

- Smoke-testing a web app after a deploy (console errors, network failures, CWV)
- Automated user flow testing (signup, checkout, onboarding)
- Visual regression checks at multiple breakpoints
- Verifying UI behavior that cannot be caught by unit or integration tests
- Scraping JS-rendered competitor or third-party pages
- Monitoring a live site for uptime or content changes

## How this differs from nearby skills

- **`bosskuai-integration-testing`**: covers API contracts, service-to-service calls, and backend integration correctness. Browser automation covers the browser layer — rendered UI, DOM interactions, network from the client perspective, visual output.
- **`bosskuai-rigorous-code-review`**: static analysis of code. Browser automation executes the running application.
- **`bosskuai-competitor-intelligence`**: strategic tracking of competitor changes. Browser automation is the execution layer that can serve that skill but has its own engineering focus (test stability, selectors, retries).

## MCP requirements

| Tool | Role | Degradation |
|------|------|-------------|
| Playwright MCP | Browser automation (required) | Without Playwright MCP, provide script templates for manual execution; note Pencil is available in Cursor as an alternative |

Note: When working in Cursor, Pencil (Cursor's built-in browser tool) can substitute for Playwright MCP for inspection tasks. For headless scripting and CI, Playwright is required.

## Workflow

Choose one or more modes based on the task:

### Mode 1 — QA Smoke Test
1. Navigate to the target URL and capture a full-page screenshot.
2. Check browser console for errors (level: error, warn).
3. Intercept network requests — flag any 4xx/5xx responses.
4. Measure Core Web Vitals (LCP, CLS, FID/INP) if performance is in scope.
5. Verify critical above-the-fold elements are visible.
6. Output: pass/fail per check, screenshots, console log excerpt.

### Mode 2 — Interaction Testing
1. Map the user flow to test (e.g., signup → verify email → first use).
2. Write Playwright steps: fill inputs, click, assert DOM state.
3. Handle async waits with `waitForSelector` or `waitForResponse` — never fixed `sleep`.
4. Test happy path first, then at least one error path (bad input, network failure).
5. Output: step-by-step pass/fail, failure screenshots.

### Mode 3 — Visual Regression
1. Define breakpoints (mobile 375px, tablet 768px, desktop 1440px minimum).
2. Take baseline screenshots if none exist; otherwise compare to stored baselines.
3. Flag pixel diffs above threshold (default: 0.1% of pixels changed).
4. Output: diff images, percentage change per breakpoint.

### Mode 4 — Competitor / Third-Party Monitoring
1. Confirm robots.txt allows crawling the target pages.
2. Set a respectful request rate (minimum 2s between requests).
3. Navigate and extract target data (pricing table, feature list, CTA copy).
4. Store a timestamped snapshot for delta comparison.
5. Output: extracted data, timestamp, diff from previous snapshot if available.

## Output format

```
## QA Report — [Site/Flow] — [Date]

### Summary
PASS / FAIL / PARTIAL — one-line verdict.

### Results by section
| Check | Status | Notes |
|-------|--------|-------|
| Console errors | PASS | 0 errors |
| Network failures | FAIL | 2× 500 on /api/cart |
...

### Screenshots
[Attached / saved to path]

### Recommended actions
Numbered list of issues requiring fix, ordered by severity.
```

## Guardrails

- Never automate login to third-party services (GitHub, Stripe, Google) without explicit user confirmation — credential exposure risk.
- Rate-limit all competitor or external scraping (minimum 2s between requests).
- Respect robots.txt — check before scraping any external domain.
- Do not commit credentials, session tokens, or cookies to any file or log.
- Prefer `data-testid` or semantic selectors over CSS class chains; document selector rationale.
- All Playwright scripts must handle timeouts gracefully — no infinite waits.
- Visual regression baselines must be stored and versioned; never auto-approve large diffs without human review.
