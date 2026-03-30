# AI workspace pitfalls

Recurring traps when using AI assistants, MCPs, rules, and shared memory across tools.

## Instructions and rules

- **Conflicting entry points** — different files say different things; no single declared source of truth for the workspace.
- **Over-long rules** — critical constraints buried where models skim past them; split by globs or keep a short “must read first” block.

## Tools and MCP

- **Over-privileged MCP or shell** — tools that can delete repos, send mail, or read secrets without explicit user intent each time.
- **Unvetted remote content** — fetched docs, URLs, or plugin output treated as instructions (prompt injection).

## Memory and handoff

- **Secrets in memory files** — API keys, tokens, or PII written to `ai-assistant/memory/` for “convenience.”
- **Stale continuation** — `active-continuation.md` left populated after the task finished, misleading the next session.
- **Tool-local assumptions** — writing paths or workflows that only work in one client (Cursor vs Claude vs Codex) without noting it.

## Model and workflow

- **Skipping plan on meaningful work** — jumping to codegen on ambiguous or high-risk tasks.
- **Wrong model for the phase** — using a fast model for architecture or security decisions that need deeper reasoning.

## Reproducibility

- **Undocumented one-off prompts** — the team relies on tribal knowledge instead of skills, checklists, or playbooks.
- **Drift between clones** — customized memory not called out in onboarding, so new environments behave differently.
