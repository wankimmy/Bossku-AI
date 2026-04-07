# Browser Automation Checklist

## Pre-work
- [ ] Automation mode selected: QA smoke test / interaction testing / visual regression / competitor monitoring
- [ ] Target URL(s) confirmed
- [ ] Playwright MCP confirmed available (or Pencil in Cursor noted as alternative)
- [ ] For external sites: robots.txt checked and crawling confirmed allowed
- [ ] For third-party login automation: explicit user confirmation obtained
- [ ] Baseline screenshots available (for visual regression mode)

## During
- [ ] Page navigation succeeds with no timeout errors
- [ ] Console errors captured and logged (level: error, warn)
- [ ] Network requests intercepted — all 4xx/5xx responses flagged
- [ ] Core Web Vitals measured if performance is in scope (LCP, CLS, INP)
- [ ] All interactive elements tested with `waitForSelector` / `waitForResponse` — no fixed `sleep`
- [ ] Happy path and at least one error path tested (interaction mode)
- [ ] Screenshots taken at each key step and at all breakpoints (375px, 768px, 1440px)
- [ ] Pixel diff threshold applied for visual regression (default ≤ 0.1%)
- [ ] Request rate limited for external scraping (≥ 2s between requests)
- [ ] No credentials, tokens, or cookies written to any file or log

## Quality gate
- [ ] QA report has clear PASS / FAIL / PARTIAL verdict per section
- [ ] All failures have a description, screenshot, and recommended action
- [ ] Selectors documented with rationale (prefer `data-testid` or semantic)
- [ ] Script handles timeouts gracefully — no infinite waits
- [ ] Visual regression baselines versioned before auto-approving diffs
- [ ] Rate limiting verified for any external domain scraping
