# BosskuAI Workspace Layer Architecture

This note explains how the workspace layer is meant to stay lean, local-first, and maintainable.

## Design Goals

- Keep the always-loaded instruction surface small.
- Make deeper behavior available on demand instead of front-loading it.
- Preserve compatibility across Claude Code, Cursor, and Codex.
- Prefer measurable helpers over broad claims.
- Keep local-first defaults and make optional remote behavior explicit.

## Instruction Surfaces

The repo uses a hub-and-spoke model:

- [`AGENTS.md`](../../AGENTS.md) is the canonical cross-tool contract.
- Tool-specific files carry only model mappings and small behavior deltas.
- Skills carry specialist workflows.
- References carry deep procedures, templates, and playbooks.
- Scripts provide validation and measurement.

If a rule appears in more than one entry file, it should usually be there only because the tool needs a tiny local reminder, not because the full rule is duplicated.

## Skill Tiers

BosskuAI separates skills into three practical tiers:

### Core skills

These are the lightweight defaults that should handle most routing:

- `bosskuai-workspace-assistant`
- `bosskuai-project-understanding`
- `bosskuai-search-first`
- `bosskuai-engineering-delivery`
- `bosskuai-rigorous-code-review`
- `bosskuai-documentation-lookup`
- `bosskuai-continuous-learning`
- `bosskuai-context-limit-continuation`

### Specialist skills

Everything else is specialist. A specialist skill should load only when the user request or repo evidence clearly points to that domain.

### Manual-only specialists

Some specialist skills are intentionally too niche to auto-route from vague wording. Framework-specific, animation-specific, or fundraising-specific skills should usually load only when the prompt names that domain directly.

### Alias skills

Deprecated alias skills stay for compatibility, but they should route directly to the maintained replacement.

## Routing Policy

- Default to one primary skill.
- Add one secondary skill only when it materially reduces risk.
- Avoid loading multiple adjacent skills that mostly restate the same workflow.
- Prefer a core skill first when the request is broad or ambiguous.
- Use [`skill-index.json`](../../skill-index.json) as the routing registry.

## Memory and Retrieval

BosskuAI memory is file-based first:

- `ai-assistant/memory/*.md` is the durable source of truth.
- `semantic-memory.sqlite3` is an index over selected memory files.
- `active-continuation.md` is ephemeral handoff state, not long-term memory.
- `active-continuation.md` ships as a starter template, but it is optional. Validation should not fail just because it has been deleted after a handoff.

The default retrieval backend is `local-hash`:

- Pros: zero extra Python dependencies, local-first, fast to sync, easy to install.
- Limits: approximate semantics, weaker than real embeddings for nuance, can overmatch generic template text.

The retrieval script is intentionally pluggable so a stronger backend can be added later without changing the memory contract.

## Measurement Philosophy

BosskuAI should make claims that can be checked locally.

The included evals measure:

- prompt-surface size for always-loaded files
- routing-fit on curated sample tasks
- retrieval hit quality on curated memory queries
- small workflow proxies that compare a plain baseline path against the BosskuAI layer

They do not measure true model intelligence or guarantee end-task accuracy. They are local health checks and regression alarms.
