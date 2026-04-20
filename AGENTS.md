# BosskuAI Workspace Layer

BosskuAI is a local-first workspace layer for Claude Code, Cursor, and Codex. It standardizes routing, memory, verification, and handoff behavior so the assistant can stay more consistent across tools and sessions.

## Activation

- The standalone word `bossku` activates BosskuAI mode for that request.
- If the user names a specific BosskuAI skill, load that skill first.
- If the task is trivial, keep routing overhead minimal.

## Always-Loaded Contract

For meaningful tasks:

1. Classify the task before acting.
2. Load one primary skill and, at most, one secondary skill from [`skill-index.json`](skill-index.json).
3. Plan first, then execute. Tool-specific files map those phases to concrete model names.
4. Read evidence before making repo-specific claims.
5. Verify the result before declaring completion.
6. Write memory only when there is a durable delta or an unfinished handoff.

For trivial tasks:

- Skip the heavy plan flow.
- Do not force memory writes.
- Stay concise.

Ambiguous scope is a hard stop:

- Ask 1-3 numbered clarification questions.
- Include one `Please answer:` line.
- Do not make broad multi-file changes until the ambiguity is resolved.

## Instruction Architecture

This file is the canonical cross-tool contract.

- [`AGENTS.md`](AGENTS.md): shared operating contract
- [`CLAUDE.md`](CLAUDE.md): Claude-specific model mapping and execution deltas
- [`.codex/AGENTS.md`](.codex/AGENTS.md): Codex-specific model mapping and execution deltas
- [`.claude/rules/bosskuai.md`](.claude/rules/bosskuai.md): thin Claude rule mirror
- [`.cursor/rules/bosskuai.mdc`](.cursor/rules/bosskuai.mdc): thin Cursor rule mirror

Do not copy long skill tables or full checklists into the tool-specific files. Advanced behavior should load only when needed through skills, references, or local scripts.

## Routing Strategy

Use the lightest routing that will still keep the work accurate.

Default core skills:

- `bosskuai-workspace-assistant`: cross-cutting or unclear-domain work
- `bosskuai-project-understanding`: repo discovery before deeper changes
- `bosskuai-search-first`: check existing options before custom building
- `bosskuai-engineering-delivery`: implementation workflow for meaningful code changes
- `bosskuai-rigorous-code-review`: reviews, regressions, and findings-first audits
- `bosskuai-documentation-lookup`: version-sensitive library or framework questions
- `bosskuai-continuous-learning`: promote durable lessons after meaningful work
- `bosskuai-context-limit-continuation`: unfinished handoff when context or quota becomes the blocker

Specialist skills are opt-in by task evidence. Load them only when the problem clearly needs their domain.

Compatibility aliases remain available for old prompts, but they should route to their replacement skill instead of carrying separate behavior.

The machine-readable router lives in [`skill-index.json`](skill-index.json). The deeper architecture note lives in [`ai-assistant/references/workspace-layer-architecture.md`](ai-assistant/references/workspace-layer-architecture.md).

## Shared Memory

Shared memory lives under [`ai-assistant/memory/`](ai-assistant/memory).

- Read [`active-continuation.md`](ai-assistant/memory/active-continuation.md) first when it contains a live handoff.
- That file is optional starter state, not a permanent required artifact. Clear it after the handoff is consumed.
- If [`semantic-memory.sqlite3`](ai-assistant/memory/semantic-memory.sqlite3) exists, query it before opening broad memory files.
- Treat vector retrieval as a narrowing tool, not as proof. Confirm important claims from the underlying source file.
- Follow [`ai-assistant/references/memory-first-handoff-protocol.md`](ai-assistant/references/memory-first-handoff-protocol.md) for read/write order and templates.

BosskuAI keeps retrieval local-first by default. The bundled `local-hash` backend is fast and dependency-light, but it is still an approximation and can miss nuanced semantic matches.

## Verification

Before saying the work is done:

- Re-check the original request.
- Review the saved diff or resulting file state.
- Run the most relevant tests, lint, or manual verification steps available.
- Call out anything you could not verify.

## Measurement

Use the built-in scripts instead of making unmeasured claims:

- `bash ./scripts/check-workspace.sh`
- `bash ./scripts/validate-skill-index.sh`
- `python3 ./scripts/eval_workspace.py`
- `python3 ./ai-assistant/scripts/vector_memory.py status`

These evals measure prompt-surface size, routing-fit proxies, and retrieval hit quality. They do not guarantee answer correctness.

## Key References

- [`WORKSPACE-ONBOARDING.md`](WORKSPACE-ONBOARDING.md)
- [`skill-index.json`](skill-index.json)
- [`ai-assistant/references/workspace-layer-architecture.md`](ai-assistant/references/workspace-layer-architecture.md)
- [`ai-assistant/references/memory-first-handoff-protocol.md`](ai-assistant/references/memory-first-handoff-protocol.md)

<claude-mem-context>
# Memory Context

# BosskuAI starter memory seed

No shared handoff is active in this starter repo. Use `ai-assistant/memory/active-continuation.md` only for unfinished work, and clear it after the handoff is consumed.
</claude-mem-context>
