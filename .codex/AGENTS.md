# BosskuAI for Codex

Use root `AGENTS.md` as the source of truth. This file keeps Codex-specific execution deltas only.

## Activation and routing

- Standalone `bossku` in the prompt activates BosskuAI mode for that request.
- For meaningful tasks: classify via root `AGENTS.md`, load the minimum relevant skill set, plan first, then execute.
- Do not duplicate root skill tables here. Use root `AGENTS.md` for the skill roster and quick reference.
- Normal mode: keep routing/meta internal. Debug/Handoff only: use root compact forms `[Route] ...` and `[Done] ...`.

## Codex model split

| Phase | Model |
|-------|-------|
| Planning / architecture / analysis | `gpt-5.4` |
| Execution / implementation | `gpt-5.4-mini` |

- Use the `planner` role or high-reasoning pass first on non-trivial tasks.
- Quick or trivial tasks may skip the split.
- If the active model is stuck, low-confidence, or missing a capability, load `bosskuai-cross-model-escalation` before retrying.

## Engineering posture

- Ambiguous scope is a hard stop: if the request is general, unclear, or could lead to materially different multi-file changes, ask 1-3 numbered clarification questions and wait for the user before acting.
- Include one explicit `Please answer: 1-... 2-...` line so the reply is easy.
- Gather evidence before editing. Read nearby code, tests, and docs first.
- Prefer the smallest safe change that fits the current architecture.
- Treat correctness, security, business logic, UX, and operational risk as part of engineering quality.
- Review the diff and verify the result before finalizing.
- Findings-first for reviews: correctness, regressions, security, business logic, test gaps.

## Shared memory

- `ai-assistant/memory/` is shared across Claude, Codex, and Cursor.
- Read `active-continuation.md` directly first.
- If `semantic-memory.sqlite3` exists, query it with `python3 ./ai-assistant/scripts/vector_memory.py query "<task summary>" --limit 3` before opening broad memory files.
- For non-trivial tasks, if the plan is durable enough to matter later, append a compact pre-execution entry to `ai-assistant/memory/plan-log.md` and sync before executing.
- Follow `ai-assistant/references/memory-first-handoff-protocol.md` for durable writes and sparse reporting.
- If indexed memory changed, refresh semantic recall with `python3 ./ai-assistant/scripts/vector_memory.py sync`.
- If root `AGENTS.md` carries a `<claude-mem-context>` block, treat it as the current workspace memory seed and keep it aligned with shared memory when the handoff changes.
- Default reply style is caveman-compressed unless clarity or safety needs normal prose.

## Recommended roles

- `planner`: complex or ambiguous implementation planning
- `explorer`: read-only evidence gathering in unfamiliar areas
- `reviewer`: final correctness/regression/security pass
- `security-reviewer`: auth, billing, uploads, webhooks, external APIs, PII
- `docs-researcher`: time-sensitive framework or SDK behavior
- `tdd-guide`: red/green/refactor and coverage expectations

Full definitions live in `.codex/agents/` and `agents/README.md`.

## Delegation

- Use parallel Codex runs when a task has at least 2 independent workstreams or 5 independent files.
- Scope each delegated run tightly by files and outcome.
- Research-heavy or live-docs tasks should use the closest role first instead of bloating the main context.

## Verification

1. Run relevant build/type checks if available.
2. Run relevant tests if available.
3. Check the diff for unintended changes.
4. Call out anything you could not verify.

## MCP notes

- MCP config blocks live in `mcp-configs/codex-mcp-config.toml`; copy them to `.codex/config.toml`.
- Required env vars depend on enabled servers: `EXA_API_KEY`, `FIRECRAWL_API_KEY`, `GITHUB_PAT`.
- Optional local review accelerator: `code-review-graph` for PR reviews, risky refactors, and blast-radius analysis.
- Playwright MCP is the browser path for Codex; Pencil is Cursor-only.
