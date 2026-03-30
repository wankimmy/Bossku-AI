# Changelog

All notable changes to this repo should be documented here.

## Release policy

- Record meaningful changes to skills, rules, onboarding docs, and public-facing examples.
- Prefer short release notes over noisy task-by-task logging.
- Group changes by capability area where possible.

## Unreleased

- Expanded `ai-assistant/references/pitfalls/` with domain files (security, performance, business-logic, product, ai-workspace), new ADRs (model split, skill expansion criteria, memory organization), `scripts/verify-skill-references.sh`, **AGENTS.md** table of contents and future-skill-area map, cross-links across entry-point rules, and maintenance guidance for time-sensitive marketing/SEO/model skills plus **bug-patterns** / **market-notes** memory templates.
- Added public onboarding and contribution files, including `WORKSPACE-ONBOARDING.md`, `CONTRIBUTING.md`, `LICENSE`, `.gitignore`, and starter examples.
- Expanded expert surfaces for planning, marketing, SEO/GEO, bug-finding, architecture, codebase analysis, polyglot engineering, and AI model selection.
- Tightened the core posture to require planning-first execution for meaningful tasks, triple-checking, and asking when material facts are unconfirmed.
- Added a vNext decision record and shared-memory updates capturing the current recommended evolution path: prioritize commands, installability, verification, and learning loops before expanding the skill roster.
- Added Phase 1 operator improvements: a new `bosskuai-search-first` skill, search-first and verification references, and Claude command shortcuts for `plan`, `verify`, and `quality-gate`.
- Added Phase 2 starter ergonomics: `scripts/install.sh`, `scripts/install.ps1`, and `scripts/check-workspace.sh`, plus onboarding updates that switch the default setup path from manual copying to script-based install and validation.
- Added Phase 3 maintenance workflows: `bosskuai-skill-stocktake`, `bosskuai-rules-distill`, new maintenance checklists/playbooks, and Claude command shortcuts for auditing skill health and proposing safe rule promotion.
- Added safe maintenance automation helpers under `ai-assistant/scripts/` for skill inventory, command inventory, rule inventory, and deterministic context collection before stocktake or rule-distillation passes.
- Added optional advisory hook-ready scripts under `ai-assistant/hooks/` for session start, pre-compact, and session-end reminders, plus security guidance that keeps hook automation opt-in and non-mutating by default.
- Added `bosskuai-continuous-learning`, a continuous-learning checklist and playbook, a Claude command, and `ai-assistant/scripts/learning-doctor.sh` to turn learning promotion and memory freshness into a repeatable workflow rather than a reminder-only policy.
- Tightened learning-memory guidance so `learning-log.md` uses structured promotion metadata, `project-understanding.md` stays aligned with current repo counts, and hooks/docs now point to the new learning hygiene pass.
