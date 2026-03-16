# Learning Log

Use this file for durable, cross-session learnings that matter in future work.
Treat it as shared memory that should improve behavior across Codex, Claude, Cursor, and any future compatible tool surface that reads this repo.

## Entries

- Keep entries brief and dated.
- Store stable patterns, decisions, and conventions.
- Do not store temporary debugging chatter.
- 2026-03-16: If model context or token limits are likely to cut work mid-task, BosskuAI should stop before truncation, preserve a compact continuation state, and ask the user to retry or continue in a fresh prompt.
