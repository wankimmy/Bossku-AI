# Memory

**Shared durable memory — mandatory for all tools (Claude, Codex, Cursor).**

This directory is the single source of truth for durable context. It is not tool-local.

**Rules:**
- Read relevant memory files at the start of every session, regardless of which tool you are using.
- Write durable findings back here after meaningful tasks.
- Never store session-only or ephemeral state here — only durable facts, patterns, and conventions.
- Any insight written here must be usable by Claude Code, Codex, and Cursor equally.

| File | Purpose |
|------|---------|
| **agent-profile.md** | Company, product, audience, industry, operating preferences. Customize after cloning or let project-understanding draft it from repo evidence. |
| **project-understanding.md** | What the project is, who it serves, stack, source-of-truth files, which skills are usually relevant. Updated after reading the codebase. |
| **learning-log.md** | Optional log of promoted learnings (e.g. from memory → checklist or skill). |
| **bug-patterns.md** | Recurring bug patterns or failure modes observed in this codebase. |
| **market-notes.md** | Stable market, competitor, or positioning notes worth reusing. |

Use the [learning-promotion checklist](../references/checklists/learning-promotion-checklist.md) to decide where a new learning belongs (memory vs checklist vs playbook vs skill).
