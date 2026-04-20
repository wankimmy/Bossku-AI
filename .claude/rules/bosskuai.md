# BosskuAI Rules — always active in this workspace

Source of truth: `AGENTS.md`. Full DoD: `CLAUDE.md`. This file carries Claude-specific deltas only.

## Core loop

- Standalone `bossku` in a prompt activates BosskuAI mode for that request.
- For meaningful work: use root `AGENTS.md` to classify the task, load the minimum relevant skill set, plan before execute, and keep routing/meta internal unless the user wants Debug/Handoff visibility.
- Normal mode: no `[TASK START]` / `[TASK END]` boilerplate. Debug/Handoff only: use root compact forms `[Route] ...` and `[Done] ...`.
- Ambiguous scope is a hard stop: ask 1-3 numbered clarification questions with a `Please answer:` line, then wait for the user before acting.

## Claude model split

- Plan / architecture / ambiguous work: `claude-opus-4-6`
- Execute / implementation: `claude-sonnet-4-6`
- Trivial tasks may skip the split.
- If the active model is stuck, low-confidence, or missing capability, load `bosskuai-cross-model-escalation` before retrying the same path.

## Shared memory

- Read `ai-assistant/memory/active-continuation.md` directly first.
- If `ai-assistant/memory/semantic-memory.sqlite3` exists, query it with `python3 ./ai-assistant/scripts/vector_memory.py query "<task summary>" --limit 3` before opening broad memory files.
- For non-trivial tasks, if the plan is durable enough to matter later, append a compact pre-execution entry to `ai-assistant/memory/plan-log.md` and sync before executing.
- Follow `ai-assistant/references/memory-first-handoff-protocol.md` for read/write thresholds and sparse reporting.
- If indexed memory changed, refresh semantic recall with `python3 ./ai-assistant/scripts/vector_memory.py sync`.
- The root `AGENTS.md` `<claude-mem-context>` block is the current session memory seed for Claude runs; keep it aligned with shared memory when the handoff changes.

## Response style

- Default reply style is caveman-compressed.
- Auto-Clarity overrides compression for security warnings, destructive actions, ambiguity, or user confusion.
- Code, commits, and PR text stay normal prose.
