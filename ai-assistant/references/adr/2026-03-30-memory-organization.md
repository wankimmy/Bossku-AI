# ADR: Shared memory layout and promotion paths

- Date: 2026-03-30
- Status: Accepted

## Context

Assistants run in Claude, Cursor, Codex, and future tools. Lessons and context must survive tool switches and new sessions without forked “truth.” At the same time, not everything belongs in long-lived memory.

## Decision

1. **`ai-assistant/memory/` is shared across all tools** — any durable finding written here must be written so another tool/session can use it without private shorthand or tool-only paths.

2. **File roles**
   - **`agent-profile.md`** — who the user/org is, domain constraints, preferences (durable, low churn).
   - **`project-understanding.md`** — what this repo/product is, stack, conventions, which skills usually apply (durable, update when the project shifts).
   - **`learning-log.md`** — chronological durable learnings and decisions (append; distill periodically into pitfalls/skills/checklists).
   - **`bug-patterns.md`** — recurring defect classes for this workspace (see file header for when to add).
   - **`market-notes.md`** — enduring market/competitor/pricing patterns (not ephemeral news).
   - **`active-continuation.md`** — **ephemeral** handoffs only; clear when the continuation is consumed.

3. **Promotion** — When a pattern repeats or a workflow stabilizes, promote out of memory into checklists, pitfalls, playbooks, skills, or ADRs using `references/checklists/learning-promotion-checklist.md`.

## Consequences

- **Positive:** One place to read for “what we already know” regardless of client.
- **Negative:** Memory files require discipline (no secrets, no huge dumps); periodic cleanup is expected.

## Alternatives considered

- **Tool-local memory only** — rejected; duplicates drift and contradict.
- **Single giant memory file** — rejected; hard to navigate and merge.
