---
name: bosskuai-i18n-l10n
description: Use this for internationalization and localization across content, locale handling, translations, formatting, right-to-left support, expansion-safe UI, and product decisions needed for multi-language experiences.
---

# BosskuAI i18n / l10n

Use this skill when the main question is **how the product behaves across languages, locales, and regions**, not just whether a string can be translated.

## How this differs from nearby skills

- **`bosskuai-ui-ux-design-to-code`**: reviews interface quality broadly; this skill focuses specifically on localization readiness, translated UX, and locale-sensitive layouts.
- **`bosskuai-product-strategy`**: decides which markets or segments to serve; this skill translates that decision into product and content requirements.
- **`bosskuai-engineering-delivery`**: implements locale handling; this skill identifies what needs to exist for a correct multilingual product.

## Mindset

- Localization is product design, content design, and engineering combined.
- English-length assumptions break interfaces fast.
- Locale handling includes formatting, fallbacks, legal copy, search, and support workflows, not only UI strings.
- Partial localization is a product decision; make the incompleteness explicit rather than accidental.

## Localization lenses

**Content externalization**
- Are strings separated from code and templates correctly?
- Are translation keys stable and understandable?
- Are screenshots, PDFs, emails, and generated content included?

**Locale and fallback behavior**
- How is locale selected: user profile, browser, URL, org setting?
- What is the fallback chain?
- What stays untranslated intentionally?

**Formatting**
- Are dates, times, currencies, numbers, addresses, names, and units locale-aware?
- Are time zones explicit where needed?
- Are pluralization and grammatical variants supported?

**Layout and interaction**
- Does the UI survive text expansion?
- Is RTL supported where relevant?
- Are truncation, line-clamping, and overflow behaviors safe?

**Operational workflow**
- Who owns translation updates?
- How are missing keys, stale translations, and release timing handled?
- Can QA verify locale-specific states?

## Workflow

1. **Read the current localization surface** — translation files, locale config, routing, formatting helpers, UI components, and design/content docs.
2. **Map locale selection and fallback** — where locale comes from, how it persists, and what happens when translations are missing.
3. **Review formatting and grammar support** — numbers, dates, currencies, plurals, RTL, and locale-sensitive copy.
4. **Review expansion-safe UI** — labels, tables, nav, forms, alerts, mobile layouts, PDFs/emails, and screenshots if available.
5. **Review translation operations** — key quality, ownership, stale translations, QA, and release timing.
6. **Name the intentional gaps** — markets not supported, partial translations, untranslated legal/support flows, or locale-limited features.
7. **Recommend the smallest safe localization slice** — what to externalize or fix first to reduce future pain.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not treat hardcoded English as acceptable just because the first market is English-speaking.
- Do not rely on string concatenation for grammatically variable content.
- Do not ignore RTL or expansion risk when a market requires it.
- Do not promise legal or cultural correctness without review from qualified humans where needed.

## Output format

```text
Localization summary:
  Supported locales: [list]
  Locale selection/fallback: [summary]
  Current content surface: [what is and is not localized]

Risks:
  Strings/content: [findings]
  Formatting: [findings]
  Layout/RTL: [findings]
  Translation workflow: [findings]

Recommended localization slice:
  [change] — [why] — [smallest safe implementation]

Intentional gaps to document:
  [gap] — [user impact]
```

## References

- `../../references/checklists/i18n-l10n-checklist.md`
- `../../references/playbooks/i18n-l10n-playbook.md`
