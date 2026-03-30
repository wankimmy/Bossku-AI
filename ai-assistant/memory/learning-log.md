# Learning Log

Use this file for durable, cross-session learnings that matter in future work.
Treat it as shared memory that should improve behavior across Codex, Claude, Cursor, and any future compatible tool surface that reads this repo.

## Entries

- Keep entries brief and dated.
- Store stable patterns, decisions, and conventions.
- Do not store temporary debugging chatter.
- 2026-03-16: If model context or token limits are likely to cut work mid-task, BosskuAI should stop before truncation, preserve a compact continuation state, and ask the user to retry or continue in a fresh prompt.
- 2026-03-26: BosskuAI should prioritize harness operations over roster expansion. The highest-value next additions are commands, install/install-check scripts, verification flows, and project-scoped learning loops rather than many new expert personas.
- 2026-03-26: Phase 1 of that direction is now in place: a search-first skill, verification references, and Claude command shortcuts. The next highest-value gap remains installability and lightweight workspace automation.
- 2026-03-26: Phase 2 is now partially in place: BosskuAI has starter install/check scripts for applying the workspace layer into real repos. The next strongest remaining gap is lightweight automation and maintenance workflows rather than more expert-surface breadth.
- 2026-03-26: Phase 3 is now partially in place: BosskuAI has maintenance workflows for skill stocktake and rules distillation. The strongest remaining gap is safe lightweight automation, especially hook-driven observation and maintenance helpers.
- 2026-03-26: The maintenance layer now has deterministic helper scripts under `ai-assistant/scripts/` for skill inventory, command inventory, and rule inventory. This is the current safe automation baseline before any always-on hooks.
- 2026-03-30: README was shortened around a single quick-setup path; `scripts/install.sh` now runs `check-workspace.sh` after copy by default (`--skip-check` / PowerShell `-SkipCheck` to opt out). Windows install runs the check when `bash` is on PATH.