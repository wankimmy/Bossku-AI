# BosskuAI

A repo-local AI workspace layer for builders using Claude Code, Cursor, and Codex.

BosskuAI gives those tools the same memory files, routing rules, skills, handoff habits, human-output checks, and token discipline. No hosted control plane. No claim that prompts magically make every answer better.


---

## What's new in v1.8.2

### P0 — Packaging correctness
- Added missing `.codex/`, `.cursor/`, `.claude/`, and `.claude-plugin/` files referenced by install/check docs.
- `scripts/check-workspace.sh . --profile full` now passes against the packaged repo.
- Claude plugin manifests now exist at `.claude-plugin/plugin.json` and `.claude-plugin/plugin.with-hooks.json`.

### P1 — Expert stack coverage
- Added first-class skills for Laravel, database engineering, VPS Docker deployment, Redis caching/queues, and content calendar planning.
- Dev profile now includes Laravel, databases, Redis, and VPS Docker deployment.
- Growth profile now includes dedicated content calendar workflow.

### P2 — Claude Opus 4.7 readiness
- Updated Claude model mapping to use `claude-opus-4-7` for planning, architecture, long-horizon coding, and complex review.

---

## What's new in v1.8.0

### P0 — Correctness fixes
- **Real TF-IDF embedder** (`ai-assistant/scripts/vector_memory.py`): replaced SHA-256 hash projection with proper IDF-weighted term scoring. Rare informative words (cofounder, ratchet, continuation) now score higher; common words (the, use, add) get low weight. Semantic score improved 0.08 → 0.10+; query ranking is now meaningful.
  - Supports 4 providers: `tfidf` (default, zero deps), `local-hash` (compat), `sentence-transformers` (neural, needs `pip install sentence-transformers`), `openai` (needs API key).
- **Shared retrieval path** (`scripts/eval_workspace.py`): eval now uses `vector_memory.retrieve_text_files()` — the same scorer as production. Green CI now means the real path was tested.
- **install.sh safety**: added `--dry-run` flag; install now refuses to target `/`, `$HOME`, or the repo root itself; target must exist before install.

### P1 — Maintenance tooling
- **`scripts/gen_skills.py`**: validates and regenerates boilerplate skill files from a canonical template. Run `--check` in CI; `--fix` to normalize drifted files. Hand-authored skills are never overwritten.
- **`scripts/rotate_learning_log.py`**: archives `Status: applied` entries older than 90 days; detects near-duplicate entries via Jaccard token overlap.
- **`scripts/validate_changelog.sh`**: CI gate — fails if deprecated_alias skills are still in install profiles, or if CHANGELOG mentions a skill that's now deprecated without a note.

### P2 — Learning loop & team memory
- **`scripts/learning_loop.py`**: reads applied+high-confidence learning entries, extracts skill mentions, and proposes trigger/keyword additions to `skill-index.json`. Run `--apply` to write an ADR and update the index.
- **`scripts/team_memory.sh`**: `tag-author`, `merge-logs` (chronological merge of two learning-log files, deduplicates by title), `check-conflicts` (detects contradicting lessons by negation + topic overlap), `install-hook` (pre-commit hook).
- **Skills indexed** (`ai-assistant/memory/vector-config.json`): 11 substantive skill SKILL.md files added to the vector index. Cofounder query now returns `score=0.354` (was zero hits).

### Optional embedding upgrade
See `requirements-optional.txt` for how to enable `sentence-transformers` (true neural embeddings, ~80MB model, no API key needed).

---

## Why use it

- keep project memory in files, not only chat history
- share one working style across Claude Code, Cursor, and Codex
- route tasks into focused skills instead of loading one giant prompt
- reduce generic AI writing with `bosskuai-human-output`
- compress noisy output/rules with `bosskuai-token-saver`
- improve prompts/workflows with a metric-based ratchet loop

## Install

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /path/to/your/project --profile core
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\path\to\your\project -Profile core
```

Profiles:

| Profile | Use when |
|---|---|
| `core` | smallest practical layer: memory, routing, search-first, human-output, token-saver, ratchet |
| `dev` | coding, review, architecture, Laravel, databases, Redis, Docker, VPS deployment, testing, GitHub workflow |
| `growth` | SEO, GEO, marketing, content calendar, launch, customer discovery, sales, competitor research |
| `design` | UI/UX, design systems, 3D, GSAP, Lenis |
| `full` | install every skill and support file |

Hooks are disabled by default:

```bash
./bosskuAI/scripts/install.sh /path/to/your/project --profile dev --with-hooks
```

## Claude Code plugin

The plugin manifest is at `.claude-plugin/plugin.json` and exposes custom paths for:

- `skills`: `./ai-assistant/skills/`
- `commands`: `./.claude/commands/`
- `agents`: `./agents/`

Test locally:

```bash
claude --plugin-dir . --debug
/plugin validate
/reload-plugins
```

Use `.claude-plugin/plugin.with-hooks.json` only if you intentionally want hook-enabled plugin testing.

## What it is not

- not a hosted agent platform
- not a replacement for tests or review
- not a guarantee of lower token usage on every task
- not a magic memory system; stale files can still mislead the model

## Key commands

```bash
bash scripts/check-workspace.sh . --profile full
bash scripts/verify-skill-references.sh .
bash scripts/validate-skill-index.sh .
python3 -S scripts/eval_workspace.py
```

## Before / after examples

Human-output:

```txt
Before: Unlock a seamless AI-powered workflow that elevates your productivity.
After: Keep the same project rules and memory across Claude Code, Cursor, and Codex.
```

Token-saver:

```txt
Before: Please make sure you carefully inspect the implementation and then provide a detailed list of issues.
After: Inspect implementation. List issues, impact, fix.
```

Ratchet loop:

```txt
Metric: approx prompt tokens
Baseline: 7,256
Change: move long skill details into playbooks
Decision: keep if routing eval still passes
```

## Docs

- `WORKSPACE-ONBOARDING.md`
- `docs/plugin-testing.md`
- `docs/benchmarks.md`
- `SECURITY.md`

## License

MIT — see `LICENSE`.

## Expert cofounder benchmark

v1.8.2 adds an expert coverage benchmark for the full cofounder stack: Laravel, Nuxt, Docker/VPS deployment, MariaDB/MySQL/SQLite/PostgreSQL/MongoDB, Redis, UI/UX, security, SEO/GEO, marketing, sales, and content calendar.

Run the full confidence check:

```bash
bash scripts/check-workspace.sh . --profile full
bash scripts/verify-skill-references.sh .
bash scripts/validate-skill-index.sh .
python3 -S scripts/eval_workspace.py
python3 -S scripts/eval_expert_coverage.py
```

See `docs/expert-benchmark-suite.md` and `docs/4.5-expert-upgrade.md`.

