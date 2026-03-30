# i18n / l10n Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Are strings and content externalized from code and templates?
- How is locale selected, persisted, and safely defaulted?
- Are dates, times, numbers, currencies, units, and pluralization locale-aware?
- Does the UI survive text expansion, truncation, and RTL layouts?
- Are emails, PDFs, screenshots, and generated content included in localization scope where needed?
- Is there a translation ownership, QA, and stale-key workflow?
- What product areas are intentionally not localized and should be documented clearly?
