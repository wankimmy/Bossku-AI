# Memory-first handoff protocol (cross-tool)

**Applies to:** Cursor, Codex, Claude Code, and any other surface using BosskuAI rules.

**Purpose:** Repo memory under `ai-assistant/memory/` is the **coordination layer** between sessions and tools. Chat history is not shared; **files are.**

Full contract: root `AGENTS.md` § Shared memory and § Local memory. This document is the **structured template** for reads and writes.

## Enforced flow for non-trivial work

For non-trivial tasks, follow this gated order:

1. Read continuation state and retrieve relevant long-term memory.
2. Plan.
3. If the plan is durable enough to matter later, append a compact entry to `ai-assistant/memory/plan-log.md`.
4. If `plan-log.md` or any other indexed memory file changed, run `python3 ./ai-assistant/scripts/vector_memory.py sync`.
5. Execute.
6. Persist outcomes, decisions, and durable learnings to the right memory file(s), usually `learning-log.md`.
7. If indexed memory changed again, run `python3 ./ai-assistant/scripts/vector_memory.py sync` again before declaring done.

This does **not** apply to trivial work.

## Trivial exception

**No required `plan-log.md` or `learning-log.md` write** when **all** are true:

- Single-line or single-hunk fix, **or** pure factual lookup, **or** yes/no with **no repo edits**, **and**
- No durable product/architecture/constraint changed.

**Default:** stay silent in normal execution mode when there was no durable memory delta.

**Only surface a skip note when needed:** Debug/Handoff mode, protocol troubleshooting, or when the user explicitly asks for memory status. Example: `Memory: unchanged (trivial prompt / no durable delta).`

## Non-trivial bar

Use the same threshold as **meaningful** / **non-trivial** work elsewhere in BosskuAI (plan-first, verification, etc.). When in doubt, treat the turn as non-trivial and write the handoff.

## Plan capture gate

Write a compact plan entry to `ai-assistant/memory/plan-log.md` **before execution** when **any** are true:

- task touches multiple files or multiple phases
- plan contains architecture, product, or workflow decisions likely to matter later
- another session/tool may need to continue from the plan
- user explicitly asks for planning first

Skip plan capture for trivial work, obvious one-step tasks, or exploratory ideas that are too unformed to be reusable.

## Read order (before substantive edits or architecture conclusions)

Read in this order **before** changing code, configs, or stating repo-specific facts:

1. `ai-assistant/memory/active-continuation.md` — **if it exists and is non-empty** (in-flight handoff from another session/tool).
2. If `ai-assistant/memory/semantic-memory.sqlite3` exists, run `python3 ./ai-assistant/scripts/vector_memory.py query "<task summary>" --limit 3` and open only the matching long-term memory sources that look relevant.
3. Fallback or expand with direct files as needed:
   - `ai-assistant/memory/agent-profile.md`
   - `ai-assistant/memory/project-understanding.md`
   - `ai-assistant/memory/plan-log.md` — recent 1–3 relevant entries when execution history or prior plans matter
   - `ai-assistant/memory/learning-log.md` — **last 1–3 dated entries** (recent decisions and next actions).
   - If the task touches defects or incidents: `ai-assistant/memory/bug-patterns.md`
   - If the task touches GTM/market: `ai-assistant/memory/market-notes.md`

Skip files that do not exist yet; create `learning-log.md` when first needed.

## Write order (before declaring done on non-trivial work)

Persist to disk **before** saying done, complete, or finished:

1. **Before execution when gated by the plan-capture rule:** Append a compact plan entry to `ai-assistant/memory/plan-log.md` using the template below, then sync vector memory.
2. **After execution — default:** Append a new **dated section** to `ai-assistant/memory/learning-log.md` using the template below.
3. **Instead of or in addition to (when appropriate):**
   - `project-understanding.md` / `agent-profile.md` — durable product, stack, or constraint facts changed.
   - `bug-patterns.md` — recurring defect class or near-miss.
   - `market-notes.md` — durable GTM/competitive note.
4. **`active-continuation.md`:** Use only for **unfinished** work that the **next** turn must continue (ephemeral). **Clear or delete** when the continuation is consumed.
5. If any indexed memory file changed, refresh semantic recall before declaring done: `python3 ./ai-assistant/scripts/vector_memory.py sync`

If you only updated `project-understanding.md` (and nothing belongs in `learning-log.md`), state that explicitly in the final reply and still mention the path.

## `plan-log.md` append template

Use an `##` heading with **ISO date + tool + short title**.

```markdown
## YYYY-MM-DD — [Cursor | Codex | Claude | other] — [Short title]

- **User goal:**
- **Why this plan was stored:** [architecture / multi-step / likely handoff / explicit planning request / other]
- **Planned approach:**
  - 
  - 
  - 
- **Expected files or surfaces:**
- **Open assumptions / risks:**
- **Status:** [planned / in-progress / superseded / completed]
- **Follow-up note:** [pointer to related `learning-log.md` entry, or `pending`]
```

## `learning-log.md` append template

Use an `##` heading with **ISO date + tool** (e.g. `## 2026-04-01 — Cursor`).

```markdown
## YYYY-MM-DD — [Cursor | Codex | Claude | other]

- **User goal (one line):**
- **What changed:** [paths, key behavior; or "none — analysis only"]
- **Commands run:** [e.g. tests, build; or "none"]
- **Verification:** [what passed / not run / manual steps]
- **Open risks / unknowns:**
- **Next actions for the next model** (numbered, imperative, include paths or commands):
  1.
  2.
- **Promotion note:** [checklist/pitfall/playbook/skill candidate, or `none`]
- **Memory files touched:** [e.g. `learning-log.md` only, or list]
```

## FOR_NEXT_MODEL (copy-paste block)

For pasting into another IDE or chat, include a short fenced block in the **assistant reply** (optional but recommended for messy handoffs):

```text
FOR_NEXT_MODEL
Tool: 
Date:
Goal:
Done:
Not done:
Next (ordered):
Files:
Risks:
```

## Relation to other artifacts

- **Session narrative:** [`session-handoff-template.md`](session-handoff-template.md) — optional scratch structure; this protocol is the **repo-persisted** requirement.
- **Promotion triage:** [`checklists/learning-promotion-checklist.md`](checklists/learning-promotion-checklist.md) after logging.
- **Hygiene:** `bash ./ai-assistant/scripts/learning-doctor.sh` before large maintenance passes when available. Use `bash ./ai-assistant/scripts/project-understanding.sh` when the workspace changed enough that `project-understanding.md` should be refreshed from source.
