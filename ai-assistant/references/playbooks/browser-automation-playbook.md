# Browser Automation Playbook

## When to use
- QA smoke tests before release: verify critical user paths work
- UI regression: confirm visual/functional state after code changes
- Competitor monitoring: scrape pricing/feature pages on a schedule
- Visual regression testing: detect unintended layout changes
- E2E test suite: document and verify user journeys

## Tool selection by platform
| Platform | Tool | Config |
|----------|------|--------|
| Claude Code | Playwright MCP | `mcp-configs/claude-mcp-servers.json` |
| Codex | Playwright MCP | `mcp-configs/codex-mcp-config.toml` |
| Cursor | Pencil MCP | Already installed at `~/.pencil/` |

## Phase 1: Setup and Pre-flight

1. **Confirm target URL and mode:**
   - QA: your own app on staging/localhost
   - Scrape: external site (competitor, data source)
   - Visual regression: any URL, comparing to baseline screenshots
2. **Check environment:**
   - Never run write operations against production
   - For external sites: check `robots.txt` at `<domain>/robots.txt`
   - Confirm auth credentials are available (env vars, not hardcoded)
3. **Define scope:** List the specific pages / flows to test. Timebox to avoid scope creep.

## Phase 2: Navigation and Capture

**Core pattern (Playwright):**
```javascript
// Always use waitForSelector — never page.waitForTimeout()
await page.waitForSelector('[data-testid="hero-cta"]', { timeout: 10000 });

// Screenshot at breakpoints
await page.setViewportSize({ width: 1440, height: 900 });
await page.screenshot({ path: 'screenshots/desktop.png', fullPage: true });

await page.setViewportSize({ width: 768, height: 1024 });
await page.screenshot({ path: 'screenshots/tablet.png' });

await page.setViewportSize({ width: 375, height: 812 });
await page.screenshot({ path: 'screenshots/mobile.png' });
```

**Capture console errors:**
```javascript
page.on('console', msg => {
  if (msg.type() === 'error') errors.push(msg.text());
});
page.on('pageerror', err => errors.push(err.message));
```

**Intercept failed network requests:**
```javascript
page.on('response', response => {
  if (response.status() >= 400) {
    networkErrors.push(`${response.status()} ${response.url()}`);
  }
});
```

## Phase 3: Interaction Testing

**Priority order:**
1. Happy path — primary user journey from start to completion
2. Error paths — empty states, validation errors, 404s
3. Edge cases — long text, special characters, mobile tap targets

**Selector strategy (in priority order):**
1. `[data-testid="..."]` — most stable, explicitly for testing
2. `role` attributes — semantic, accessible
3. Text content — fragile but acceptable for stable copy
4. CSS classes — last resort, breaks on refactors

**Form submissions:**
```javascript
await page.fill('[data-testid="email-input"]', 'test@example.com');
await page.click('[data-testid="submit-btn"]');
await page.waitForURL('**/success', { timeout: 15000 });
```

## Phase 4: Visual Regression

1. **Establish baseline:** Take screenshots on the known-good state. Store as `screenshots/baseline/`.
2. **After changes:** Take new screenshots at same breakpoints.
3. **Compare:** Use pixel diff or visual comparison tool. Flag diffs > 2% of pixels.
4. **Triage diffs:** Intentional change vs unintended regression.

## Phase 5: QA Report

```markdown
## Browser QA Report: <URL> | <Date>

### Summary
Overall verdict: PASS / FAIL / PARTIAL
Pages tested: X | Passed: X | Failed: X | Warnings: X

### Results by Section
| Section | Viewport | Status | Notes |
|---------|----------|--------|-------|
| Homepage | 1440 | PASS | Screenshot: ./ss/home-desktop.png |
| CTA button | 375 | FAIL | Selector timeout after 10s |
| Checkout | 768 | PARTIAL | Form submits but confirmation page missing |

### Console Errors
- TypeError: Cannot read property 'x' of undefined | Source: app.js:123

### Network Errors
- 404 GET /api/user/profile

### Recommendations
1. Fix #cta-btn selector or add data-testid — Priority: high
2. Add /api/user/profile endpoint — Priority: critical
```

## Rate Limiting Rules (external scraping)

- Minimum 2 seconds between requests to external domains
- Maximum 10 pages per run per domain
- Respect `Crawl-delay` in robots.txt
- Identify your scraper: set a descriptive User-Agent
- If blocked (429 / CAPTCHA): stop, do not retry automatically

## Security Guardrails

- Never write credentials to screenshot files, reports, or logs
- Mask sensitive input field values in screenshots if possible
- Store session tokens in env vars — never in test files
- Do not run against production environments for tests with write operations
- Do not click "Delete" / destructive actions in automated tests without explicit confirmation

## Common Pitfalls

**Fixed sleeps** — `page.waitForTimeout(3000)` is flaky. Use element/network/URL-based waits.

**Brittle CSS selectors** — break on refactors. Use `data-testid` instead.

**Missing console error capture** — silent JS errors cause phantom failures. Always listen.

**Testing on production** — one wrong click can delete real data. Always use staging.

**Not capturing screenshots on failure** — without artifacts, failures are hard to debug.
