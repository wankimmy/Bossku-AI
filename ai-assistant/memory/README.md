# Memory

**Shared durable memory — mandatory for all tools (Claude, Codex, Cursor).**

Read relevant files at session start. Write durable findings back after meaningful tasks. Never store ephemeral state here.

| File | Purpose |
|------|---------|
| **agent-profile.md** | Company, product, audience, industry, operating preferences |
| **project-understanding.md** | What the project is, stack, source-of-truth files, relevant skills |
| **learning-log.md** | Durable lessons and promotion decisions |
| **active-continuation.md** | Ephemeral handoff for context/usage-limit breaks; clear when done |
| **bug-patterns.md** | Recurring defect classes for this workspace |
| **market-notes.md** | Durable market / competitor / positioning notes (6+ month horizon) |

Use the [learning-promotion checklist](../references/checklists/learning-promotion-checklist.md) to decide where a learning belongs.

For hygiene, run `bash ./ai-assistant/scripts/learning-doctor.sh` to catch stale memory, empty high-value files, and consumed continuation state.
