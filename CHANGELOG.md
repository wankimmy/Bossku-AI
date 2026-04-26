## v1.8.3 — Honest depth pass

### Removed (legacy duplicate cleanup)

- Deleted 22 legacy unprefixed playbooks whose only difference from their `bosskuai-*` counterparts was a trimmed-out "Output expectation" section. List: `agent-security-hardening`, `analytics-metrics`, `api-design`, `code-revamp`, `codebase-analysis`, `data-architecture`, `devops-iac`, `docker`, `engineering-delivery`, `github-workflow`, `gsap-animation`, `i18n-l10n`, `launch-commercialization`, `legal-compliance`, `lenis-smooth-scroll`, `operations`, `paid-acquisition-monetization`, `polyglot-engineering`, `sales-strategy`, `search-first`, `seo-geo`, `skill-creator`.

### Renamed (kept content, removed name confusion)

- 14 legacy playbooks with substantive unique workflow content renamed from `<name>-playbook.md` to `<name>-detailed-playbook.md` and linked from their `bosskuai-*` counterparts via a "Further reading" section. Affected: `3d-web-development`, `browser-automation`, `bug-finding`, `competitor-intelligence`, `customer-discovery`, `deep-research`, `design-systems`, `financial-modeling`, `growth-experiment`, `investor-prep`, `lead-intelligence`, `marketing-growth`, `nuxt-development`, `rapid-prototype`.

### Patched

- 3 references in `ai-assistant/skills/cofounder/SKILL.md` and 2 sibling playbooks updated to point at the surviving `bosskuai-*` versions.

### Added

- **Real-content Laravel playbook.** `bosskuai-laravel-development-playbook.md` rewritten from a 93-line checklist to a 415-line reference: 10 worked anti-pattern → fix pairs (N+1, missing Form Request authorization, soft-delete uniqueness across MariaDB/MySQL/PostgreSQL/SQLite, non-idempotent jobs, webhook signature/replay, transaction-after-commit event ordering, tenant scoping leaks via relations, API Resource leakage, Octane singleton state, `env()` outside config), with code, verification commands, and a production audit matrix. All `must_cover` keywords from `evals/expert-benchmark-cases.json` preserved.
- **Adversarial routing eval.** `evals/adversarial-routing-cases.json` (8 cases) and `scripts/eval_adversarial_routing.py`. Uses symptom-language prompts that intentionally avoid the trigger keywords listed in `skill-index.json`. Designed to expose the gap between keyword-tuned routing and natural user phrasing. Ships in diagnostic mode (always exits 0) so it does not block CI; pass `--strict` to gate.
- **`docs/adversarial-routing.md`** — what the eval measures, why it ships RED at 0/8 in this release, and three concrete paths to close the gap (richer symptom triggers, embedding fallback, model-as-router).

### Honest framing

- This release does **not** claim a quality improvement on the existing benchmarks (they were already 12/12 and will stay 12/12 — the underlying routing logic is unchanged). It claims: less duplicate bloat, one marquee playbook with real depth instead of headline coverage, and a new diagnostic that shows where routing is actually weak.
- `eval_workspace.py` and `eval_expert_coverage.py` continue to pass at the same rates as v1.8.2.

---



- Added expert cofounder benchmark task bank.
- Added `scripts/eval_expert_coverage.py`.
- Added cofounder decision-quality playbook and checklist.
- Added expert cofounder stack checklist.
- Deepened Laravel, Nuxt, database, VPS Docker deployment, Redis, UI/UX, and SEO/GEO guidance.
- Strengthened routing keywords for coding, database, deployment, security, SEO/GEO, marketing, sales, and content calendar work.
- Added docs for 4.5/5 quality threshold and remaining limitations.

# Changelog

## v1.8.2 — Expert stack coverage and package correctness

### Added
- Missing Claude/Cursor/Codex workspace files: `.claude/`, `.cursor/`, `.codex/`.
- Claude plugin manifests under `.claude-plugin/`.
- `bosskuai-laravel-development` for Laravel best practices, Eloquent, queues, policies, testing, security, and performance.
- `bosskuai-database-engineering` for MariaDB, MySQL, PostgreSQL, SQLite, MongoDB, constraints, indexes, query plans, and migrations.
- `bosskuai-vps-docker-deployment` for production Docker Compose on VPS, reverse proxy, SSL, backups, health checks, and rollback.
- `bosskuai-redis-caching-queues` for Redis caching, Laravel queues, locks, rate limits, sessions, and worker operations.
- `bosskuai-content-calendar` for campaign calendars, Malay-English social content, hooks, CTAs, cadence, and metrics.

### Changed
- Updated `CLAUDE.md` to map complex planning, architecture, long-horizon coding, and repeated failed attempts to `claude-opus-4-7`.
- Expanded dev and growth install profiles with the new expert skills.
- Added routing eval cases for Laravel, database engineering, VPS Docker deployment, Redis, and content calendar routing.

### Fixed
- `scripts/check-workspace.sh . --profile full` no longer fails on missing `.claude`, `.cursor`, `.codex`, or plugin files.

## 1.8.0

### Deprecations in 1.8.0

- `bosskuai-root-cause-investigation` → replaced by `bosskuai-bug-finding` (narrower scope was redundant).
- `bosskuai-project-management` → replaced by `bosskuai-planning-execution`.
- `bosskuai-social-content-calendar` → merged into `bosskuai-marketing-growth`.
- `bosskuai-caveman` → replaced by `bosskuai-token-saver`.

## 1.7.0

- Made Claude hooks opt-in by default.
- Added hook enable/disable scripts for Bash and PowerShell.
- Updated Claude Code plugin manifest to expose skills, commands, and agents.
- Added hook-enabled plugin manifest example.
- Added install profiles: core, dev, growth, design, full.
- Added `bosskuai-ratchet-loop` skill and checklist.
- Moved large SKILL.md content into playbooks to reduce prompt bloat.
- Added plugin testing and benchmark docs.
- Added GitHub Actions validation with evals and profile smoke tests.
- Added `SECURITY.md`.

# Changelog

## 1.5.0 - Human Output and Token Saver Cleanup

- Added `bosskuai-human-output` to remove generic AI writing patterns from README, docs, UI microcopy, and public copy.
- Added `bosskuai-token-saver` as the serious public-facing replacement for the old caveman compression skill.
- Kept `bosskuai-caveman` as a deprecated compatibility alias.
- Added anti-AI writing, anti-AI UI, and token-saver checklists.
- Tightened the UI/UX skill with a stronger anti-generic-SaaS design gate.
- Rewrote `AGENTS.md` and `README.md` to reduce always-loaded prompt surface and remove generic AI SaaS tone.
- Updated validation/eval commands to support `python3 -S` for environments where normal Python startup/shutdown loads slow site hooks.


All notable changes to this repo should be documented here.

## Release policy

- Record meaningful changes to skills, rules, onboarding docs, and public-facing examples.
- Prefer short release notes over noisy task-by-task logging.
- Group changes by capability area where possible.

## Unreleased

- Added `bossku` as a documented activation keyword across `AGENTS.md`, tool-specific entry points, onboarding docs, examples, and routing metadata so users can trigger BosskuAI rules plus automatic skill selection with a simple prompt cue.
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
- Added `ai-assistant/scripts/relearn-project-understanding.sh` so users can snapshot current understanding, preserve memory, and generate a refresh prompt for `bosskuai-project-understanding` + `bosskuai-codebase-analysis` after BosskuAI itself changes.
- Promoted six former future-skill placeholders into dedicated skills with matching checklists and playbooks: `bosskuai-api-design`, `bosskuai-devops-iac`, `bosskuai-data-architecture`, `bosskuai-i18n-l10n`, `bosskuai-analytics-metrics`, and `bosskuai-legal-compliance`.
- Added `bosskuai-root-cause-investigation` with supporting checklist/playbook for comprehensive bug investigation using business-logic tracing plus DB state, logs, queues, jobs, webhooks, and runtime evidence.

