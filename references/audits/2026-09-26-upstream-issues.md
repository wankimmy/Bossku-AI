# Upstream issue drafts from the 2026-09-26 skill review

Vendored skills stay byte-identical to upstream, so these bugs are fixed upstream, not here. Each entry is ready to file; Bossku routes around the problem until a re-vendor picks up the fix. Line numbers refer to the vendored copies as of this review.

## coreyhaines31/marketingskills

1. **ai-seo: crawler table mixes training bots with search bots.** `ai-seo/SKILL.md:160-163`, `:432` and `references/platform-ranking-factors.md:121,131` present GPTBot, ClaudeBot and Google-Extended as the bots that decide AI citations. OpenAI documents `OAI-SearchBot` for ChatGPT search ("Sites that are opted out of OAI-SearchBot will not be shown in ChatGPT search answers", https://developers.openai.com/api/docs/bots); Anthropic separates ClaudeBot (training), Claude-User and Claude-SearchBot (https://support.claude.com/en/articles/8896518); Google-Extended "does not impact a site's inclusion in Google Search" (https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers). Split the table into search/citation bots and training bots.
2. **schema, directory-submissions, competitors, ai-seo: removed Google rich results.** FAQ rich results stopped appearing on 2026-05-07 (https://developers.google.com/search/docs/appearance/structured-data/faqpage), HowTo was deprecated in 2023 (https://developers.google.com/search/blog/2023/08/howto-faq-changes), and the sitelinks search box was removed on 2024-11-21 (https://developers.google.com/search/blog/2024/10/sitelinks-search-box). Affected: `schema/SKILL.md:3,56,60-61,84-85`, `references/schema-examples.md:47`, `directory-submissions:43,79,84,246`, `competitors:226`.
3. **emails, cold-email: no sender requirements.** Neither covers SPF/DKIM/DMARC, From alignment, RFC 8058 one-click unsubscribe, or the spam-rate ceiling (below 0.30%, aim below 0.10%; https://support.google.com/a/answer/81126). `cold-email/references/benchmarks.md:30` gives only the 0.1% target. Outlook consumer inboxes enforce the same trio above 5,000 messages a day (https://techcommunity.microsoft.com/blog/microsoftdefenderforoffice365blog/strengthening-email-ecosystem-outlook%E2%80%99s-new-requirements-for-high%E2%80%90volume-senders/4399730).
4. **churn-prevention: vacated FTC rule and old Stripe facts.** `SKILL.md:370` and `references/cancel-flow-patterns.md:300-304` cite the FTC click-to-cancel rule as in force; the Eighth Circuit vacated it on 2025-07-08 (https://www.cooley.com/news/insight/2025/2025-07-11-click-to-cancel-just-got-cancelled-eighth-circuit-vacates-entirety-of-ftcs-negative-option-rule). Cite ROSCA and state auto-renewal laws instead. `references/dunning-playbook.md:111,113,283-285`: Stripe is now Billing > Revenue recovery > Retries, "8 tries within 2 weeks" (https://docs.stripe.com/billing/revenue-recovery/smart-retries).
5. **directory-submissions: dofollow check cannot see nofollow.** `:131` uses `curl -sIL` (headers only) and concludes "If absent, the link is dofollow". Fetch the body and inspect the anchor's `rel`.
6. **Pack-wide: links into an unshipped `tools/` tree.** 78 links such as `../../tools/REGISTRY.md` and `../../tools/integrations/*.md`, plus `node tools/clis/google-ads.js` (`ad-creative:403`), point outside the skill folders. Make them optional or absolute URLs.
7. **prospecting: compliance gaps.** `references/compliance.md:28-36` treats GDPR legitimate interest as enough for cold email without the ePrivacy/PECR layer; `:75` says InMail is outside GDPR although the prospect list itself is personal data.
8. **pricing: no deliverable.** No output format (compare sales-enablement's table) and no usage-based or AI-credit pricing with a cost-to-serve margin floor.
9. **video: undocumented Hyperframes API.** `video/SKILL.md:59-78` shows `import { render } from "hyperframes"` with a `frames` array; the upstream README documents `npx hyperframes init/preview/render` and HTML `data-start`/`data-duration` clips (https://github.com/heygen-com/hyperframes).
10. **analytics: pre-GA4 wording.** `:143` "Mark conversions" (GA4 now says key events) and `:241` IP anonymization (a Universal Analytics setting).

## emilkowalski/skills

1. **animate, find-animation-opportunities: hand-offs the model cannot follow.** `animate/SKILL.md:3,73` and `find-animation-opportunities/SKILL.md:3` tell the model to invoke `pick-ui-library` and `review-animations`, which set `disable-model-invocation: true`. Reword as "recommend the user run `/pick-ui-library`" or drop the flag.
2. **emil-design-eng: stale Motion import.** `:163` imports from `'framer-motion'` while `:160` names Motion; the upgrade guide says `motion/react` (https://motion.dev/docs/react-upgrade-guide). The description at `:3` has no "Use when".

## oso95/scroll-world

1. **Missing bootstrap.** `SKILL.md:43-44` says to install per the `higgsfield-generate` skill, which the pack does not include. Inline the Higgsfield CLI install command.

## affaan-m/ECC

1. **accessibility: garbled description and wrong criteria.** The description's second line starts with a stray "standards." fragment. `:43` labels a visible focus indicator SC 2.4.11 (in WCAG 2.2 that is Focus Not Obscured; visible focus is 2.4.7), `:17` presents Focus Appearance (2.4.13, AAA) as an AA standard, and 3.3.8 Accessible Authentication and 3.2.6 Consistent Help are missing (https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/). Related skills at `:144-147` do not exist in this subset.
2. **e2e-testing:** recommends `networkidle` as the good wait (`:53,59,190`) and mixes `browser.startTracing` with an expected trace `.zip` (`:207-213` vs `:278`). Unverified against current Playwright docs.
3. **python-testing:** async fixture under plain `@pytest.fixture` (`:490`, fails in pytest-asyncio strict mode; unverified); the "FastAPI/Flask" example is Flask-only (`:664-683`).
4. **error-handling:** the Go `QueryRow` snippet at `:272` does not compile.
5. **mcp-server-patterns:** dead link at `:12`; no Python SDK section although Python is a first-class SDK.
6. **mysql-patterns, vue-patterns:** dead cross-references (`mysql-patterns:409-413`, `vue-patterns:469-471`).

## miqdadbadjuber/anti-slop

1. **Large-text threshold.** `antislop/SKILL.md:300`, `antislop-human/SKILL.md:26,65`, and `contrast-check.py:10` use "18px+" for large text. WCAG 2.2 large scale is 18pt or 14pt bold, which is 24px regular or about 18.7px bold (https://www.w3.org/TR/WCAG22/#dfn-large-scale), so 18-23px regular text passes at 3:1 when it should fail.

## Leonxlnx/taste-skill

1. **Self-contradiction on fake data.** Section 4.9 (`:327-330`) bans invented precise numbers; 9.D (`:618-619`) asks for "organic, messy data (47.2%)" and invented names that "sound real"; `:279` suggests inventing a logo mark. Resolve in favour of 4.9.
2. **Stale package facts.** `:1019` `npm install uswds` (now `@uswds/uswds`, https://designsystem.digital.gov/documentation/developers/); `:90,991` recommend `@material/web`, whose README says it is in maintenance mode.
3. **Missing and heavy material.** Section 12 (`:835-893`) points at `skills/taste-skill/blocks/`, which does not ship. The appendices (`:983-1207`) would cost less as a reference file loaded only when a real design system is chosen. The default fonts are mostly commercial with no licensing check.

## Nutlope/hallmark

1. **Ship theme tokens with the skill.** The 21-theme catalog lives in `site/css/tokens.css` outside `skills/hallmark/`, so skill-only installs have theme names without values. Bossku vendors the file at `site/css/tokens.css` as a workaround.

## Graphify-Labs/graphify

1. **Over-broad trigger with side effects.** The description "Use for any question about a codebase" can fire on ordinary questions; with no graph present it installs `graphifyy` with `--break-system-packages` and writes `graphify-out/`. Narrow the description to "when `graphify-out/` exists or the user asks for a graph" and drop the flag by default.

## browser-use/browser-use (x402)

1. **Wallet key handling.** `x402/SKILL.md:131-132,143` print the generated private key although `:122` says "Show the address only"; `:78` invites pasting a private key into chat; `:264` auto-tops-up after one confirmation. Never print keys and make top-up opt-in. Bossku excludes x402 from installs until this is fixed.

## cobusgreyling/loop-engineering

1. **Hollow bodies.** `changelog-scan:9` and `draft-release-notes:9` defer to a "Grok version" that is not part of the pack; ship the contract with the skill.
2. **loop-constraints:** `:33` cites a `docs/safety.md` that does not exist in the pack.
