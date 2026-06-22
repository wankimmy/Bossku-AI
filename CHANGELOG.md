# Changelog

## v1.13.0 - Cross-repo portability and session token optimization

- **Plugin now carries the full harness**: `.claude-plugin/plugin.json` exposes `commands` (11 commands moved from `.claude/commands/` to `commands/`), `hooks` (new `hooks/hooks.json`), and a curated `agents` array — so skills, commands, agents, and hooks all work in **any** repo the plugin is enabled for, not just this one.
- **Portable hooks**: plugin hooks use `${CLAUDE_PLUGIN_ROOT}` paths. New `session-start-context.sh` (SessionStart) injects the compact BosskuAI contract + resolved memory home into model context once per session in every repo — replacing the per-prompt stderr reminder that never reached the model. `common.sh` gained `resolve_bossku_home()` ($BOSSKU_HOME → project → sibling Bossku-AI checkout → plugin root) so shared memory works from sibling projects with zero config. `write_hook_output` no longer echoes hook-input JSON to stdout; edit counter is now session-scoped. Repo-local `.claude/settings.json` hooks emptied to avoid double-firing (mcp-health-check stays opt-in there — per-MCP-call bash spawn is too hot for a default).
- **Curated subagents**: plugin exposes the 24 editor-appropriate agents only; the 10 Laravel runtime pipeline personas (orchestrator, executor, auditor, clarification, designer, model-router, writer, final-reviewer, direct-answer, skill-detector) no longer leak into the editor's Agent list. Frontmatter normalized on editor agents (valid Claude Code `tools`, `model: opus` instead of runtime role names).
- **Token optimization**: 25 longest/deprecated skill descriptions rewritten as tight trigger statements (7,313 → 4,606 chars; ~680 tokens saved per session, every session, keywords preserved). Deprecated aliases reduced to one-liners.
- Versions aligned at 1.13.0 across `.claude-plugin/plugin.json`, root `plugin.json`, both marketplaces, and `skill-index.json`. Removed stray broken `Bossku-AI` symlink at repo root.

## v1.12.0 - Loop architectures, prompt optimizer, 5 new agents, and flows

- Added `bosskuai-autonomous-loops` (ECC port): loop architecture catalogue — sequential `claude -p` pipelines, infinite agentic loop, continuous PR loop, de-sloppify pattern, Ralphinho RFC-driven DAG — plus a new section documenting BosskuAI's own runtime revise loop (`max_revision_rounds`, ExecutorStuckDetector) as the built-in pattern.
- Added `bosskuai-prompt-optimizer` (ECC port, heavily remapped): advisory-only prompt diagnosis and rewriting that matches intent/scope/stack to the bosskuai skill+agent roster and outputs ready-to-paste optimized prompts.
- Added 5 editor-mode agent contracts: `loop-operator` (drives autonomous loop architectures: exit conditions, context bridging, stall detection), `performance-optimizer` (measure → change one variable → re-measure ratchet), `database-reviewer` (migration/rollback/index/tenant-scope gate), `code-simplifier` (de-sloppify pass after green, separate context from author), `incident-responder` (stabilize → verify → prevent with blameless postmortem).
- Enhanced core agents: orchestrator gained a **Flows table** (10 task-shape → chain → loop-owner routes) and council/introspection wiring in runtime-core; planner gained DAG decomposition rules + complexity-tier table; executor gained de-sloppify handoff + introspection-on-cap + Laravel skill wiring; security-reviewer gained laravel-security/prompt-injection-defense/tenant-isolation wiring; auditor gained agent-architecture-audit, laravel-verification gate, and database-reviewer delegation.
- skill-index.json v1.12.0 (105 skills); AGENTS.md roster and specialist list updated; plugin.json 1.3.0.

## v1.11.0 - ECC agent-stack, decision, and Laravel skills

- Added 4 agent-stack/decision skills ported from ECC (affaan-m/ECC): `bosskuai-agent-architecture-audit` (12-layer agent diagnostic, grounded to the BosskuAI pipeline), `bosskuai-agent-introspection` (capture → diagnose → contained recovery for stuck/degraded agent runs), `bosskuai-council` (four-voice decision council), `bosskuai-context-budget` (context overhead audit incl. runtime persona injection).
- Added 3 manual-only Laravel specialists for the `app/` backend and any Laravel repo: `bosskuai-laravel-security`, `bosskuai-laravel-tdd`, `bosskuai-laravel-verification`.
- Folded the ECC continuous-learning-v2 instinct model into `bosskuai-continuous-learning`: atomic confidence-weighted instincts under `ai-assistant/memory/instincts/`, promotion ladder, runtime parallel to `LearningEngine`.
- Registered all 7 skills in `skill-index.json` (v1.11.0, 103 skills) and `AGENTS.md` (new "Agent-stack & decision skills" and "Laravel stack specialists" rosters).
- Verified greptile skills (`bosskuai-greptile-review-loop`, `bosskuai-pr-check`) remain in sync with upstream greptile-skills (latest: edited-comment handling, GitLab + Perforce support).

## Unreleased

- **Agentic tool-use loop (`App\Services\Agents\AgenticToolLoop`)**: the iterative counterpart to the single-shot executor — a ReACT-style driver that lets a model read → edit → verify → read-the-error → fix in one task, observing each tool result before deciding the next step (opencode's defining behaviour, which Bossku-AI's one-shot JSON executor lacked). Model-agnostic JSON protocol (`{tool_calls:[...], done, final}`) so it works across every Ollama/OpenAI-compatible model, not just ones with native function calling. Reuses the existing `ToolRegistry` (all writes still flow through approval/auto-apply governance) and `ModelFallbackService`. **Closes the verify leg**: new governed `run_command` runtime tool lets the loop run allowlisted commands (`php artisan test`, `composer test`, `npm test`, `git status`, …) via the hardened `ProjectCommandRunner` (allowlist + forbidden-token + path/timeout bounds — no new execution surface) so the model can run tests, read the failure, and fix; `run_command` is executor-only (denied to read-only roles), editor aliases `bash`/`shell` map to it. Hard iteration cap (`bossku.agentic_max_iterations`, default 12, clamp [1,50]) + identical-call stuck detection so a confused model can't spin. Runnable via `php artisan bosskuai:agent-loop "<task>"`. **Now wired into the pipeline as a selectable executor mode** (`bossku.executor_mode` / `BOSSKUAI_EXECUTOR_MODE`, default `single_shot`): set it to `agentic` and the main executor step runs the loop via `App\Services\Agents\AgenticExecutorAdapter`, which performs the work then reconstructs a pipeline-shaped `execResult` from the run's file-write approvals and `run_command` tool calls — so the auditor, evidence, revise, and learning machinery downstream run unchanged. Three idempotency guards (`_files_already_applied` / `_commands_already_run` in `applyExecutorFileChanges`, `applyExecutorCommands`, `applyOrPauseForExecutorApprovals`) prevent double-apply. Agentic mode auto-falls back to single-shot when per-change user approval is required (it applies during the loop). Covered by `AgenticToolLoopTest` (done/cap/stuck/observation-feedback) + `AgenticExecutorAdapterTest` (execResult reconstruction from approvals/tool-calls, stuck→partial mapping), all with a scripted fake model.
- **Coding-tool upgrades (opencode parity)**: (1) **Line-numbered, paginated `file_read_safe`** — returns `<n>: <content>` with `offset`/`limit` (default/cap 2000 lines, 2000 chars/line) + `total_lines`/`truncated`, replacing the blind 8 KB cut; executor contract warns to strip the `<n>: ` prefix when quoting `old_string`. (2) **ripgrep-backed `file_search`** — fast, gitignore-aware, returns matching line + line number; falls back to the in-PHP scan when `rg` is absent (`bossku.ripgrep_path`, `bossku.allow_ripgrep_search`). (3) **Post-edit diagnostics** — new `App\Services\Project\ChangedFileDiagnostics` runs the cheapest authoritative check per changed file (`php -l`, JSON, YAML) right after apply; failures fold into `known_issues` + downgrade status to `partial` (via `ExecutorEvidenceSupport::mergeApplyReport`), driving the auditor/revise loop instead of shipping a file that won't parse — the single-shot analogue of opencode's read-diagnostics-after-edit LSP step. Covered by `ChangedFileDiagnosticsTest` + ToolRegistry read/search/edit tests.
- **Surgical edit engine (opencode-grade reliability)**: new `App\Services\Project\FileEditEngine` — a PHP port of opencode's multi-strategy replacer cascade (exact → line-trimmed → block-anchor → whitespace-normalized → indentation-flexible → escape-normalized → multi-occurrence). The executor can now emit `files_changed[].edits` (`[{old_string, new_string, replace_all?}]`) instead of reproducing whole files or emitting brittle unified diffs: it quotes the exact snippet to change and the engine locates it even when whitespace/indentation drift, rejecting not-found/ambiguous edits with loud, actionable errors instead of silently dropping the change. Wired through `FileWriteApplier::extractAfterContent` (edits preferred over diff), `ExecutorService` contract + result normalization, and the strict validation gate (`ExecutorResponseParser`). New `file_edit` runtime tool in `ToolRegistry` (routes through the same approval/auto-apply governance); `edit` editor alias now maps to surgical `file_edit` (was whole-file `file_write_proposed`); `write` still maps to `file_write_proposed`. Read-only roles (auditor, planner, reviewers, writers) denied `file_edit`. Covered by `FileEditEngineTest` (11 cases) + permission tests.
- Docs: agentic orchestration layer — `agents/orchestrator|executor|auditor|final-reviewer|model-router|skill-detector.md`, root `memory/`, `skills/`, `playbooks/`, `integrations/{cursor,claude-code,codex,opencode}`, `ui/*-spec.md`, expanded `docs/*` (installation, quickstart, examples, architecture, FAQ, comparison).
- Rules: mandatory `[BOSSKUAI]` response indicator in `AGENTS.md`, `.cursor/rules/bosskuai.mdc`, `.claude/rules/bosskuai.md`, `CLAUDE.md`, `.codex/AGENTS.md`, `packages/bossku-ai` SKILL.
- Routing: align `app/config/bossku_models.php` fallbacks with documented Gemini / Qwen / DeepSeek ordering; extend `ai-assistant/config/model-router.yaml` with backup hints.
- Audit round 2: merge duplicate model-flow sections in `AGENTS.md`, remove `<claude-mem-context>` footer, `evals/indicator-compliance-fixtures.json` + indicator regex in `scripts/eval_workspace.py` (v1.9.6), expand `agents/skill-detector.md`, `auto_memory` ↔ schema bridge in `memory/schema.md`, **Memory Used** chip in `RoutingDashboard.vue`, decision-tree mermaid in `docs/comparison.md`.

## v1.9.5 - Reliability Hardening and 5/5 Audit Fixes

- Fixed orchestrator memory subprocess calls so they never inherit stdin and cannot hang during `scripts/bosskuai run`.
- Added structured run completion with outcome/audit memory save and active-continuation clearing.
- Added `scripts/bosskuai continuation show|claim|clear` for honest cross-tool takeover between Claude Code, Cursor, and Codex.
- Added `scripts/bosskuai session start|end` plus session-log support for every tool.
- Added memory doctor, memory autopromote, stronger secret redaction, non-blocking stdin reads, and Claude Stop-hook extraction.
- Strengthened risk detection and routing for tenant isolation/cross-organization data exposure.
- Added 9 missing cofounder expert skills: SaaS billing ops, tenant isolation security, observability/SRE, QA automation, Malaysia PDPA privacy, cost optimization, customer success/support, prompt-injection defense, and eval-driven agent improvement.
- Expanded expert coverage evals from 12 to 21 cases.
- Updated version to v1.9.5 and skill count to 86.

## v1.9.3 — Permanent vector memory + always-on model router

## v1.9.4 - No-UI Command Center, Run Orchestration, and Memory Inbox

- Added `scripts/bosskuai` CLI command center.
- Added structured Plan → Execute → Audit → Memory run packets under `ai-assistant/runs/`.
- Added always-on model routing scripts with risk escalation for auth, payments, security, privacy, migrations, production, secrets, and data-loss work.
- Added local memory extraction into `ai-assistant/memory/inbox.jsonl`.
- Added memory approval/rejection CLI flow that promotes approved memories into durable memory and syncs vector DB.
- Added `ai-assistant/runtime/system_state.json` for command-center state without a UI.
- Added cron example for memory extraction, vector sync, and eval checks.


Adds the missing runtime layer for the workflow: durable cross-tool memory and a plan/execution/audit model policy.

### Added

- **`ai-assistant/scripts/auto_memory.py`** — local-first memory CLI for Claude Code, Cursor, and Codex.
  - `query` wraps vector DB retrieval.
  - `remember` writes durable entries to the right memory file and syncs vector DB.
  - `capture` supports hook payloads and deduplicates repeated events.
  - `status` shows memory health.
- **`ai-assistant/memory/conversation-memory.md`** — indexed cross-tool conversation/request memory.
- **`ai-assistant/memory/durable-memory.md`** — indexed stable decisions, preferences, and constraints.
- **`ai-assistant/memory/conversation-log.jsonl`** — raw local audit trail, intentionally not indexed.
- **`ai-assistant/skills/bosskuai-permanent-memory-orchestration/SKILL.md`** — routing skill for permanent memory, vector DB sync, and cross-tool context.
- **`ai-assistant/references/always-on-model-router.md`** — frontier plan → lower-cost execute → frontier audit protocol.
- **`.claude/settings.hooks.example.json`** — Claude Code hook config that captures user prompts and syncs vector memory automatically.

### Changed

- `AGENTS.md`, `CLAUDE.md`, `.cursor/rules/bosskuai.mdc`, and `.codex/AGENTS.md` now enforce:
  1. retrieve memory first,
  2. plan with frontier model,
  3. execute with lower-cost model when safe,
  4. audit with frontier model,
  5. save durable memory and sync vector DB.
- `vector-config.json` now indexes `conversation-memory.md` and `durable-memory.md` with retrieval hints for past conversations and permanent memory.
- `scripts/install.sh` core profile includes the permanent-memory orchestration skill.
- `scripts/enable-hooks.*` now enable auto memory capture plus vector sync.

### Honest framing

- Claude Code can use hooks for automatic capture when enabled.
- Cursor and Codex do not expose one universal hook surface in every environment, so BosskuAI enforces the behavior through rules/config plus the shared `auto_memory.py` CLI.
- This captures future conversations and durable summaries. It cannot recover old chat history from tools that never wrote it to the repo.

### Validation

Run before release:

```bash
bash scripts/check-workspace.sh . --profile full
bash scripts/validate-skill-index.sh .
python3 -S scripts/eval_workspace.py
python3 ai-assistant/scripts/auto_memory.py remember --tool test --kind durable "Decision: test durable memory write."
python3 ai-assistant/scripts/auto_memory.py query "test durable memory" --limit 3
```

---

## v1.8.9 — Engineering principles skill (Karpathy framing)

A new tiny skill that codifies four widely-recognized engineering principles as a routable BosskuAI skill, with attribution to the public framing they came from.

### Added

- **`ai-assistant/skills/bosskuai-engineering-principles/SKILL.md`** (~140 lines). The four principles:
  1. **Think Before Coding** — state assumptions, ask when uncertain, present alternatives, push back when warranted.
  2. **Simplicity First** — minimum code, no speculative abstractions, no unrequested flexibility.
  3. **Surgical Changes** — every changed line traces to the request; no drive-by refactors.
  4. **Goal-Driven Execution** — transform vague tasks into verifiable goals; multi-step plans state per-step `verify:` clauses.

  Plus an output contract (Assumptions / Plan / Diff scope / Verification / Out of scope) and a specialist-routing table that hands off depth to the marquee playbooks after framing.

- Indexed in `skill-index.json` with 9 triggers (`engineering principles`, `apply principles before coding`, `think before coding`, `simplicity first`, `surgical changes`, `goal-driven execution`, `Karpathy principles`, `frame the work`, `before I write code`) and 6 keywords (`principles`, `minimum diff`, `speculative abstractions`, `verifiable goal`, `drive-by refactor`, `test-first`).

### Attribution

The four-principle structure is adapted from [Andrej Karpathy via forrestchang/andrej-karpathy-skills](https://github.com/forrestchang/andrej-karpathy-skills) (MIT). Cited explicitly at the top of the SKILL.md. The Karpathy file itself is one CLAUDE.md with four named principles; this skill operationalizes those principles with BosskuAI's existing routing and output-contract conventions, plus a specialist hand-off table.

### Why a new skill instead of merging into existing ones

Considered three options before shipping:

- **A — do nothing.** Coding-best-practices, rigorous-code-review, ask-clarifying-questions, and the `/implement` slash command already cover all four principles in pieces. Adding a skill would duplicate.
- **B — new tiny skill (chosen).** Captures the public Karpathy framing as a recognizable named unit. Loaded only when triggered, so adds zero tokens to the always-loaded surface. Routes to specialists for actual implementation depth.
- **C — full rewrite of `bosskuai-coding-best-practices`.** Reorganize that skill under the four-principle headers. Real merge, but tears up a working skill for a frame change.

Picked B. The skill is intentionally narrow: framing only, no code-quality detail, no review rules. Those live in their existing skills. This skill explicitly references them in the output contract and "specialist routing" sections so the cofounder hands off correctly after framing.

### Routing safety

Verified that the new skill:
- Routes correctly on 6/6 positive prompts (`apply engineering principles`, `think before coding`, `simplicity first`, `make changes surgical`, `goal-driven execution with verification`, `Karpathy principles`).
- Does **not** hijack 5/5 negative prompts that should reach other specialists (Laravel audit, code review, function naming, clarification, SQL optimization).

### Validation

| Check | v1.8.8 | v1.8.9 |
|---|---|---|
| `check-workspace.sh` (full) | PASS | PASS |
| `verify-skill-references.sh` | PASS | PASS |
| `validate-skill-index.sh` | 74 skills | 75 skills |
| `eval_workspace.py` routing-fit | 18/18 | 18/18 |
| `eval_workspace.py` retrieval / workflow | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_workspace.py` prompt surface delta | −87.91% | −87.91% |
| `eval_expert_coverage.py` | 12/12 | 12/12 |
| `eval_adversarial_routing.py` | 8/8 GREEN | 8/8 GREEN |
| `eval_routing_generalization.py` | 7/8 GREEN | 7/8 GREEN |

Always-loaded surface unchanged because the new skill is loaded on-demand via routing, not eagerly. All existing benchmarks unchanged because the new skill's triggers are scoped to terms not used by other skills.

### Honest framing — what this release does NOT claim

- It does NOT claim BosskuAI was missing these principles. They were already enforced in pieces across `bosskuai-coding-best-practices`, `bosskuai-rigorous-code-review`, `bosskuai-ask-clarifying-questions`, `cofounder-decision-quality-playbook`, and the `/implement` slash command. What this release adds is the recognizable public **framing** as a named skill, plus an output contract that makes the four principles visible in coding-task responses.
- It does NOT claim the skill makes Bossku produce better answers. The honest test for that is `eval_llm_quality.py` from v1.8.4 — run it on a coding task case once with this skill loaded and once without, compare graded scores. That measurement is the user's next step, not this release's claim.
- It does NOT add the Karpathy file verbatim. Pasting a 60-line CLAUDE.md as a skill would have duplicated what already exists. The four-principle frame is the only thing taken; the BosskuAI specifics (output contract, specialist routing, when-not-to-use rule, honesty rule against virtue-signaling the principles) are this workspace's adaptations.

---



A self-hosted, local-only UI for inspecting and managing the workspace. Read-mostly, no LLM calls, no external network. Single Python script + static HTML/JS — zero pip dependencies.

### Run

```bash
python3 scripts/dashboard.py
# open http://127.0.0.1:8765
```

Default bind is loopback only. Pass `--host 0.0.0.0 --port N` to expose (firewall yourself).

### Added — `scripts/dashboard.py`

Stdlib-only HTTP server with the following endpoints:

| Endpoint | Method | Purpose |
|---|---|---|
| `/` | GET | Serves the static dashboard |
| `/api/health` | GET | Liveness check + workspace path |
| `/api/workspace` | GET | Full skill graph: nodes (74 skills with depth, category, triggers, playbook refs), edges (cross-references + trigger overlaps) |
| `/api/memory` | GET | All files in `ai-assistant/memory/` with content |
| `/api/vectordb` | GET | Status of `semantic-memory.sqlite3` (table counts, top sources, byte size, or "not built yet") |
| `/api/vectordb/query?q=…&k=…` | GET | Run a real retrieval query through `vector_memory.retrieve_from_conn` against the persistent DB. Returns scored chunks with component breakdown (semantic/lexical/heading/document_name/intent/recency/source). |
| `/api/evals` | GET | Runs all 4 eval scripts as subprocesses (workspace, expert_coverage, adversarial_routing, routing_generalization) and parses headlines |
| `/api/understand` | POST | Generates a framed `bosskuai-project-understanding` prompt file at `<target>/.bosskuai-understand-prompt.md`. **No LLM call** — user runs it in Claude Code. |
| `/api/sync/dry-run` | POST | Computes a per-file plan (create/overwrite/skip) for syncing skills+refs to a target project. Returns a `confirm_token` required for apply. |
| `/api/sync/apply` | POST | Applies a previously-dry-ran sync. Writes a timestamped backup to `<target>/.bosskuai-backup/<YYYYMMDDTHHMMSSZ>/` before any overwrite. Requires matching `confirm_token`. |
| `/api/vectordb/reindex` | POST | Runs `python3 ai-assistant/scripts/vector_memory.py sync` |

### Added — `dashboard/` (static frontend)

- `dashboard/index.html` — single-page, 5 tabs (Skill Graph / Memory / Vector DB / Evals / Actions), CSS variables match Anthropic-aligned dark UI.
- `dashboard/app.js` — vanilla JS + D3 v7 from CDN. Force-directed graph with marquee outline, node radius proportional to trigger count, color toggle (category vs depth), edge toggles (cross-reference / trigger overlap), click-to-detail side panel showing description, triggers, keywords, and referenced playbooks with line counts.

### Safety guarantees, baked in

These were explicitly tested before shipping. All POST endpoints honor:

- **Source-workspace protection**: refuses to sync to the source workspace itself, or any parent of it.
- **Empty/root path protection**: rejects empty `target_path` or `/`.
- **Confirm token**: sync apply requires a token returned by dry-run with the same target+scope. Mismatched token = no-op.
- **Auto-backup before overwrite**: every overwrite writes a copy of the existing file to `<target>/.bosskuai-backup/<timestamp>/` first. No silent destructive copies.
- **`# DO NOT SYNC` marker**: any file in target containing this string in its content is skipped, lets you protect customizations.
- **Path traversal**: static file serving resolves and `relative_to(DASHBOARD_DIR)` checks every request; `../`, URL-encoded `%2e%2e`, and absolute paths all return 404.
- **No LLM calls anywhere**: the "understand" button writes a prompt file. Running it requires the user to open it in Claude Code. No API keys consumed by the dashboard.
- **Loopback-only by default**: explicit `--host 0.0.0.0` required to expose. Server prints a clear warning when bound to non-loopback.

### What the dashboard shows

- **Skill graph**: 74 nodes, 81 relations (51 cross-references + 30 trigger overlaps). Cofounder is the hub with 28 connections. Marquee skills (the 9 deepened across v1.8.3–v1.8.5) are outlined white. Color encodes depth (DEEP/OK/THIN) or category (engineering/infra/runtime/data/security/growth/sales/design/operating/research/quality/meta). Drag to reorganize, zoom and pan.
- **Memory**: full text of every file in `ai-assistant/memory/` — `learning-log.md`, `agent-profile.md`, `project-understanding.md`, etc. Live, no caching.
- **Vector DB**: SQLite stats (chunk counts per source, table summary, total size). Plus a query box that runs real retrieval through the production scorer — same `retrieve_from_conn` path that Bossku itself uses. Score breakdown shows semantic / lexical / heading / document_name / intent / recency / source components. Honest note in the UI: indexed scope is configured via `vector-config.json` `include` list and is intentionally narrow (memory + 11 specific SKILL.mds, not all 91 playbooks).
- **Evals**: live status of all 4 eval suites. "Run all" re-runs them in subprocess.
- **Actions**: three buttons described above.

### Honest framing — what this dashboard is and isn't

This is a **maintainer tool**, not a runtime upgrade. It does not make Bossku produce better answers. It makes the workspace easier to understand, audit, and propagate. The five things it actually changes:

1. You can see the workspace as a graph, not just files.
2. You can browse memory + vector DB without opening SQLite manually.
3. You can verify all evals pass without remembering 4 separate commands.
4. You can sync skills to other projects with a dry-run + backup safety net (was previously manual `cp -r` with no rollback).
5. You can generate `project-understanding` prompts for new projects without typing the framing yourself.

What this does NOT do: live session telemetry (would require wrapping Claude Code/Codex), token cost tracking per session (would require API hooks), real-time sub-agent visualization (would require Task tool instrumentation). Those are layer 2 and layer 3 work, deliberately deferred.

### Validation

| Check                                            | v1.8.7 | v1.8.8 |
|--------------------------------------------------|--------|--------|
| `check-workspace.sh` (full)                      | PASS   | PASS   |
| `verify-skill-references.sh`                     | PASS   | PASS   |
| `validate-skill-index.sh`                        | PASS   | PASS   |
| `eval_workspace.py` routing-fit                  | 18/18  | 18/18  |
| `eval_workspace.py` retrieval / workflow         | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_workspace.py` prompt surface delta         | −87.91% | −87.91% |
| `eval_expert_coverage.py`                        | 12/12  | 12/12  |
| `eval_adversarial_routing.py`                    | 8/8 GREEN | 8/8 GREEN |
| `eval_routing_generalization.py`                 | 7/8 GREEN | 7/8 GREEN |
| Dashboard endpoint smoke tests                   | —      | 6/6 (health, workspace, memory, vectordb, vectordb/query, evals) |
| Dashboard safety smoke tests                     | —      | 5/5 (source-protection, parent-protection, empty-rejection, bad-token-rejection, full-flow-with-backup) |
| Path traversal block                             | —      | 3/3 (`..`, `%2e%2e`, `/etc/passwd` all 404) |

All evals unchanged — the dashboard is additive observability, not a workspace mutation.

---



This release targets the real win for token reduction: prompt caching on Claude Code, plus minimum-context loading per sub-agent. It also ships the eval harness to measure whether the optimization actually works in production usage, instead of relying on architecture-diagram intuition.

### Surface where this matters

The user runs Claude Code (VS Code extension), Codex CLI, and Cursor's own chat. These behave differently for token economics:

- **Claude Code**: Anthropic API direct, prompt caching available, ~90% discount on cache hits, sub-agent dispatch via Task tool.
- **Codex CLI**: OpenAI API, no equivalent caching, no sub-agent dispatch.
- **Cursor's own chat**: opaque routing layer, caching status not verifiable, no sub-agent dispatch.

The optimizations in this release apply primarily to Claude Code. Codex stays single-call (already lean). Cursor's own chat is treated as a single-call surface.

### Changed — slash commands now enforce explicit context budget

Each of the three deep-mode commands (`/audit`, `/decide`, `/implement`) was tightened to specify exactly what each sub-agent loads and what it does NOT load:

- **Loaded into a sub-agent**: ONE specialist SKILL.md, ONE referenced playbook, the artifact, the task framing, the output contract.
- **NOT loaded**: the cofounder skill (the dispatcher already framed the task), other specialist skills (each sub-agent stays in its lane), `AGENTS.md`/`CLAUDE.md` (in system prompt via runtime), conversation history beyond the framing, other playbooks the SKILL.md merely cross-references.

This matters for two reasons:
1. **Cache hits**: a stable, identical prefix across sub-agent calls is what triggers Anthropic's prompt cache. Loading different skill combinations per sub-agent breaks the cache.
2. **Accuracy**: focused-context reasoning genuinely outperforms juggled-context reasoning. A sub-agent with one specialist's playbook applies that playbook's anti-patterns rigorously; one with five skills loaded thinks generically.

This is the single most impactful change in the release.

### Added — token-budget eval harness

- **`evals/token-budget-cases.json`** — 3 standard tasks covering decision, audit, and implement flows.
- **`scripts/eval_token_budget.py`** — two-step pipeline:
  - `--emit-prompts` writes one task prompt per case. The user runs each prompt in BOTH modes (single-call AND the case's deep-mode command) and records token usage to JSONs under `evals/runs/<run-id>/usage/`.
  - `--score` reads usage JSONs and produces a deterministic comparison: total input tokens, cached tokens, output tokens, wall time, effective cost (using Anthropic's ~90% cache discount), and the deep-mode/single-call ratio per case.

The schema matches the `usage` field Anthropic returns in API responses (`input_tokens`, `cache_read_input_tokens`, `output_tokens`). Claude Code shows these per call; the user copies the numbers into the JSON.

A smoke test was run with simulated usage records:

| Case | Single-call cost | Deep-mode cost | Ratio | Wall time ratio |
|---|---|---|---|---|
| cofounder-decision (`/decide`) | 10,500 | 14,550 | 1.39× | 1.62× |
| laravel-checkout-audit (`/audit`) | 21,500 | 31,050 | 1.44× | 1.78× |
| redis-cache-fix (`/implement`) | 18,000 | 23,500 | 1.31× | 1.54× |

Real numbers depend on session continuity (5-minute cache TTL), workspace stability (no mid-session edits to AGENTS.md/SKILL.md), and dispatch correctness. The eval is designed to catch regressions on any of those.

### Added — caching-and-token-budget guide

- **`docs/caching-and-token-budget.md`** — explains:
  - Surface capability matrix and where caching is verifiable.
  - The four levers, ranked by honest impact: prompt caching (biggest), minimum-context loading (medium), always-loaded surface budget (small), lazy playbook loading (small).
  - Worked example showing ~50% real cost reduction on a `/audit` flow with caching vs without.
  - Per-surface guidance: Claude Code gets all four levers; Codex gets raw token reduction only; Cursor's own chat treated as single-call.
  - "How to actually measure this" — a five-minute test the user can run today on a single task in both modes and confirm whether caching is hitting.

### Honest framing — what this release does NOT claim

- This release does NOT claim deep-mode is always cheaper than single-call. With prompt caching working well, deep-mode runs ~1.1–1.5× a single call (estimated). Without caching, deep-mode runs ~2–3× a single call. Whether that premium is worth it depends on whether the deep-mode flow produces materially better answers, which is what `eval_llm_quality.py` (from v1.8.4) measures.
- The token-budget numbers in the smoke test above are simulated, not real. The real numbers come from running the eval against actual Claude Code sessions. The harness is the artifact this release ships; the measurement is the user's next step.
- This release does NOT add multi-agent flows for Codex or Cursor. On those surfaces, the architecture is single-call by design — running multi-agent flows there means the user typing sequential prompts that re-pay context cost, which is worse than single-call on every axis.

### Validation

| Check                                            | v1.8.6 | v1.8.7 |
|--------------------------------------------------|--------|--------|
| `check-workspace.sh` (full)                      | PASS   | PASS   |
| `verify-skill-references.sh`                     | PASS   | PASS   |
| `validate-skill-index.sh`                        | PASS   | PASS   |
| `eval_workspace.py` routing-fit                  | 18/18  | 18/18  |
| `eval_workspace.py` retrieval / workflow         | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_workspace.py` prompt surface delta         | −87.91% | −87.91% |
| `eval_expert_coverage.py`                        | 12/12  | 12/12  |
| `eval_adversarial_routing.py`                    | 8/8 GREEN | 8/8 GREEN |
| `eval_routing_generalization.py`                 | 7/8 GREEN | 7/8 GREEN |
| `eval_token_budget.py`                           | —      | scaffold (run-graded) |

Existing benchmarks unchanged. Slash command edits added zero tokens to the always-loaded surface (the commands are loaded only when invoked).

---



### Added — three opt-in deep-mode flows for Claude Code

This release ships the architecture pattern people often request as "cofounder is the orchestrator, every skill is an agent" — but only for the cases where multi-agent dispatch genuinely beats single-call. Default flow remains single-call routing on every surface.

- **`.claude/commands/audit.md`** — `/audit` slash command. Fan-out parallel review across 2–4 specialists (e.g. Laravel + cybersecurity + database for a checkout audit), then synthesize with explicit rules for forcing decisions on specialist disagreements rather than splitting the difference. Cost: 3–5× a normal call. Latency: 30–90s.
- **`.claude/commands/decide.md`** — `/decide` slash command. Propose-then-critique. Cofounder generates a recommendation, a separate sub-agent attacks it using the failure-modes table from `cofounder-decision-quality-playbook.md`, the cofounder revises or defends. Cost: ~2× call. Latency: +10–25s. Designed to break the sycophancy of a single model critiquing its own output.
- **`.claude/commands/implement.md`** — `/implement` slash command. Write-then-review for non-trivial diffs (payments, auth, multi-tenancy, migrations, queues, webhooks). Implementer writes code + tests; a separate reviewer sub-agent applies `bosskuai-rigorous-code-review` rules plus the relevant specialist playbook's worked anti-patterns. Cost: ~2× call. Latency: +15–40s.

### Added — multi-agent architecture documentation

- **`docs/multi-agent-architecture.md`** — explains:
  - Surface capability matrix (Claude Code has native sub-agent dispatch via Task tool; Codex requires manual prompt chaining; Cursor doesn't support it natively at all).
  - Default single-call flow that works on every surface.
  - The three Claude Code deep-mode flows with diagrams and cost/latency expectations.
  - Equivalent manual prompt sequences for Codex and Cursor users.
  - Honest section "Why not full multi-agent" — error compounding (0.9⁵ = 59% reliability), latency multiplication, synthesis as bottleneck, and why specialist-as-agent is the same model reading different docs (the gain is focused context, not separate expertise).
  - "When NOT to use deep-mode" — routine questions, time-sensitive requests, missing artifact, cost not justified by stakes.
  - How to measure whether deep-mode actually beats single-call on the same task using `eval_llm_quality.py` from v1.8.4.

### Surface-specific wiring

- `cofounder` SKILL.md updated to point at the three slash commands and the architecture doc.
- `.codex/AGENTS.md` augmented with the equivalent manual prompt-sequence patterns for Codex users.
- `.cursor/rules/bosskuai.mdc` augmented with the same patterns for Cursor users.

The same workspace serves all three surfaces. Deep-mode is a Claude Code superpower; on Codex and Cursor the user invokes the same workflow manually.

### Honest framing

This release does not rebuild the architecture. It adds three explicit multi-agent flows for the cases where they earn their cost (cross-domain audits, hard-to-undo decisions, non-trivial code), and documents clearly why the rest of the workspace stays single-call. The "every skill is an agent, cofounder dispatches everything" pattern was considered and rejected — the math doesn't work for routine requests, and the literature on multi-agent gains supports targeted use, not global dispatch.

The one outstanding step: run `eval_llm_quality.py` twice on the same case bank — once single-call, once with `/decide` — and compare graded scores. If `/decide` doesn't add at least 0.10 average score over single-call cofounder mode, the deep-mode flow isn't earning its cost and should be removed. The honest test, not the architecture diagram, decides whether this stays in.

### Validation

| Check                                            | v1.8.5 | v1.8.6 |
|--------------------------------------------------|--------|--------|
| `check-workspace.sh` (full)                      | PASS   | PASS   |
| `verify-skill-references.sh`                     | PASS   | PASS   |
| `validate-skill-index.sh`                        | PASS   | PASS   |
| `eval_workspace.py` routing-fit                  | 18/18  | 18/18  |
| `eval_workspace.py` retrieval / workflow         | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_expert_coverage.py`                        | 12/12  | 12/12  |
| `eval_adversarial_routing.py`                    | 8/8 GREEN | 8/8 GREEN |
| `eval_routing_generalization.py`                 | 7/8 GREEN | 7/8 GREEN |

Existing benchmarks unchanged because routing logic and skill content are unchanged. Deep-mode is invoked by user choice, not by the routing system.

---



This release reflects what BosskuAI actually is: a cofounder agent that takes a founder from idea to MVP across engineering, product, design, security, marketing, sales, operations, and GTM. The depth pass focused on the skills the cofounder calls *most* and that previously had the worst depth-to-importance ratio.

### Deepened — cofounder-shape skills

- **`cofounder-decision-quality-playbook.md`** — 74 → 221 lines. Was framework-only; now includes 3 worked decisions (good-vs-bad answers for "should we build team workspaces", "SEO isn't working", "investor wants AI"), stage-aware defaults table (pre-product / MVP / pre-PMF / scaling), 8 named cofounder failure modes, when-to-push-back rules, when-to-ASK rules, harder routing cases, and explicit honesty rules.
- **`bosskuai-product-strategy-playbook.md`** — replaces the 23-line `product-discovery-playbook.md` with a 181-line strategy reference. Three diagnostic questions, 3 worked decisions (paying customer feature request, bloated PRD, competitor announcement), 8 MVP-stage failure modes, scope-cut techniques in escalating order, JTBD shaping pattern, anti-patterns specific to early-stage AI/agent products. Linked from both `cofounder` and `bosskuai-product-strategy` SKILL.md files.
- **`bosskuai-cybersecurity-risk-playbook.md`** — 154 → 365 lines. Existing STRIDE/OWASP framework preserved; appended 3 worked threat models (Stripe webhook, multi-tenant data leak, secrets in version control), an MVP-stage risk-vs-theater table that says when to skip WAF/SOC2/pentesting at small scale, the 10 things that actually matter at MVP scale in priority order, privacy/data-minimization rules, auth-specific anti-patterns table, and an incident-response checklist of things to have ready *before* an incident.
- **`bosskuai-mongodb-playbook.md`** — 140 → 464 lines. 8 worked anti-patterns (unbounded array on hot doc, ESR rule for compound indexes, `$lookup` blowing up memory, schema-less drift, write-concern for critical writes, resumable migrations, keyset pagination on ObjectId, Atlas-specific gotchas), production audit matrix, and an "honest answer: don't use Mongo if SQL is the right tool" decision section.

### Redundancy purge — 14 files removed

Honest investigation in this release revealed that what looked like redundant skills were actually **deliberate alias stubs** for backwards compatibility (`bosskuai-caveman` → `token-saver`, `bosskuai-project-management` → `planning-execution`, `bosskuai-root-cause-investigation` → `bug-finding`, `bosskuai-social-content-calendar` → `marketing-growth`). Those are kept — deleting them would break old prompts.

What WAS actually redundant:

- **2 stale legacy stub playbooks** that the v1.8.3 detail-rename pass missed (no incoming references, content fully covered elsewhere): `bug-finding-detailed-playbook.md`, `marketing-growth-detailed-playbook.md`.
- **12 orphan checklists/pitfalls** that no skill or other reference points at: `documentation-lookup-checklist`, `general-known-pitfalls`, plus checklists for `browser-automation`, `competitor-intelligence`, `customer-discovery`, `deep-research`, `financial-modeling`, `growth-experiment`, `investor-prep`, `lead-intelligence`, `nuxt-development`, `rapid-prototype`. These were content from earlier versions left after their consumer skills moved to other patterns.

### Skill-overlap audit — no merges performed

Empirically checked all 74 skills for trigger/keyword overlap. The largest overlap (4 shared terms) was the deliberate `caveman` ↔ `token-saver` alias pair. No two non-alias skills had meaningful overlap. The "merge `codebase-analysis` / `project-understanding` / `documentation-lookup`" suggestion from prior release notes was **wrong** — these are sequential not redundant, and the SKILL.md descriptions correctly cross-reference them. Documenting this so the next reviewer doesn't repeat the bad call.

### Validation

| Check                                            | v1.8.4 | v1.8.5 |
|--------------------------------------------------|--------|--------|
| `check-workspace.sh` (full)                      | PASS   | PASS   |
| `verify-skill-references.sh`                     | PASS   | PASS   |
| `validate-skill-index.sh`                        | PASS   | PASS   |
| `eval_workspace.py` routing-fit                  | 18/18  | 18/18  |
| `eval_workspace.py` retrieval / workflow         | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_expert_coverage.py`                        | 12/12  | 12/12  |
| `eval_adversarial_routing.py`                    | 8/8 GREEN | 8/8 GREEN |
| `eval_routing_generalization.py`                 | 7/8 GREEN | 7/8 GREEN |

### Marquee depth — final state

| Playbook | v1.8.2 | v1.8.5 | Δ |
|---|---|---|---|
| Laravel | 93 | 415 | +322 |
| Nuxt | 184 | 382 | +198 |
| Redis caching/queues | 78 | 386 | +308 |
| VPS Docker | 96 | 433 | +337 |
| Database (SQL) | 81 | 386 | +305 |
| MongoDB | 140 | 464 | +324 |
| Cybersecurity | 154 | 365 | +211 |
| Cofounder decision quality | 74 | 221 | +147 |
| Product strategy | 23 | 181 | +158 |

Nine load-bearing playbooks now in the 180–470 line range with worked examples, audit matrices, and verification steps. The same template — "wrong-shape vs right-shape with one verification step" — runs through all of them.

### What this release does NOT claim

- An LLM-quality eval was not run on this release. The harness from v1.8.4 (`scripts/eval_llm_quality.py`) is the way to grade actual model quality; this release adds material the harness can grade, but the grading itself is a separate artifact.
- The remaining 65 skills (most below "marquee" status) were not touched. They range from adequate to thin; the next depth pass would target whichever 3-5 are actually called most in production traffic, which requires real usage data.

---



### Added — playbook depth pass on remaining marquee skills

Same depth-rewrite treatment that v1.8.3 applied to Laravel, now applied to:

- **`bosskuai-nuxt-development-playbook.md`** — 189 → 366 lines. 11 worked patterns: SSR fetch waterfalls, hydration mismatches, `useFetch` vs `useAsyncData`, `routeRules` for SSR/SSG/ISR/SWR per path, SEO metadata in setup vs onMounted, dynamic sitemap sources, hydration payload bloat, Core Web Vitals (LCP/CLS/INP) regressions, slow Nitro routes, Nuxt 4 migration gotchas. Production audit matrix included.
- **`bosskuai-redis-caching-queues-playbook.md`** — 78 → 386 lines. 11 worked patterns: stampede / single-flight / SWR, cross-tenant cache key collisions, missing TTLs, eviction-policy mismatch when cache and queue share Redis, `timeout >= retry_after` double-execution, locks without expiry, failed-job alerting, Horizon tags, worker memory leaks, SLOWLOG reviews, cache invalidation strategies. Production audit matrix included.
- **`bosskuai-vps-docker-deployment-playbook.md`** — 96 → 433 lines. 10 worked patterns: published DB ports as the perimeter risk, root containers, `depends_on` without health checks, SSL renewal that lapses silently, untested backups, bind-mount data loss on `down -v`, 502s on first deploy, log rotation, missing `queue:restart` on deploy, rollback paths. Production audit matrix included.
- **`bosskuai-database-engineering-playbook.md`** — 81 → 386 lines. 10 worked patterns plus a cross-driver reality check matrix: composite index column order, soft-delete uniqueness across MariaDB/MySQL/PostgreSQL/SQLite (3 different idiomatic fixes), online-safe ALTER, EXPLAIN reading patterns with a symptom→cause→fix table, JSON column indexing per driver, `ON DELETE` policy choices, UUIDv4 vs ULID/UUIDv7, counter cache drift, duplicate/unused index audit, Laravel migration safety multi-step pattern.

### Added — measurable routing improvement, not just a benchmark patch

- **31 + 19 symptom triggers** added to `skill-index.json` across 7 skills. These describe symptoms in user language ("worker retries", "container can't reach", "feels like a template") rather than skill jargon. They were chosen to read like real recurring user phrasings, not to game the benchmark — see the new generalization eval below for proof.
- **`evals/adversarial-routing-cases.json`** (was 0/8 in v1.8.3) is now **8/8** GREEN.
- **`evals/adversarial-routing-generalization.json`** is a NEW set of 8 fresh symptom-language cases that were NOT used to design the triggers. Score: **7/8 (88%)** GREEN. The 1 remaining failure (a paraphrase that uses no shared keywords with the skill index) is preserved honestly rather than overfitted; closing it requires either an embedding fallback or a model-as-router pass, both documented in `docs/adversarial-routing.md`.
- `scripts/eval_routing_generalization.py` runs the new fresh-case eval. Diagnostic by default; `--strict` to gate.

### Added — LLM-quality eval scaffolding

This release ships the missing piece called out in v1.8.3 review notes: an eval that grades **actual model answers**, not workspace coverage.

- **`evals/llm-quality-cases.json`** — 7 senior-engineer tasks across Laravel, Nuxt, Redis, VPS Docker, database engineering, and the cofounder persona. Each task has a weighted rubric (5–6 criteria) and a `must_avoid` list of cargo-cult fixes that hard-fail the case if the candidate recommends them.
- **`scripts/eval_llm_quality.py`** — three-step pipeline:
  - `--emit-prompts` writes task .txt files for the candidate model.
  - `--emit-rubrics` writes grading prompts (with the candidate's answer inlined) for a grader model or human.
  - `--score` reads grade JSONs and produces a deterministic weighted-score report. Must-avoid violations zero out the case score regardless of rubric coverage.
- **`docs/llm-quality-eval.md`** — explains why the harness deliberately externalizes the grader (reproducibility + honesty), the schema for grade JSONs, the scoring math, and what "true 4.5" looks like on this eval.

### Validation

| Check                                            | v1.8.3 | v1.8.4   |
|--------------------------------------------------|--------|----------|
| `check-workspace.sh` (full)                      | PASS   | PASS     |
| `verify-skill-references.sh`                     | PASS   | PASS     |
| `validate-skill-index.sh`                        | PASS   | PASS     |
| `eval_workspace.py` routing-fit                  | 18/18  | 18/18    |
| `eval_workspace.py` retrieval / workflow         | 8/8 / 3/3 | 8/8 / 3/3 |
| `eval_workspace.py` prompt surface delta         | −89.97% | −89.97% |
| `eval_expert_coverage.py`                        | 12/12  | 12/12    |
| `eval_adversarial_routing.py`                    | 0/8 RED | 8/8 GREEN |
| `eval_routing_generalization.py` (NEW)           | —      | 7/8 GREEN |
| `eval_llm_quality.py`                            | —      | scaffold (run-graded) |

### Honest framing

This release closes three of the four gaps the v1.8.3 review identified. The fourth — actually scoring high on `eval_llm_quality.py` — requires a real model run with an external grader, which is the next release's work, not this one's. What v1.8.4 delivers is the harness to measure that score reproducibly, so the next number we publish will be defensible.

---



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

