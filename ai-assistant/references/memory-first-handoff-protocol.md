# Memory-first handoff protocol (cross-tool)

**Applies to:** Cursor, Codex, Claude Code, and any other surface using BosskuAI rules.

**Purpose:** Repo memory under `ai-assistant/memory/` is the **coordination layer** between sessions and tools. Chat history is not shared; **files are.**

Full contract: root `AGENTS.md` § Shared memory and § Local memory. This document is the **structured template** for reads and writes.

## Trivial exception

**No required `learning-log.md` append** when **all** are true:

- Single-line or single-hunk fix, **or** pure factual lookup, **or** yes/no with **no repo edits**, **and**
- No durable product/architecture/constraint changed.

**Still required:** End the assistant reply with one explicit sentence, e.g. `Memory: unchanged (trivial prompt / no durable delta).` — do **not** skip silently.

## Non-trivial bar

Use the same threshold as **meaningful** / **non-trivial** work elsewhere in BosskuAI (plan-first, verification, etc.). When in doubt, treat the turn as non-trivial and write the handoff.

## Read order (before substantive edits or architecture conclusions)

Read in this order **before** changing code, configs, or stating repo-specific facts:

1. `ai-assistant/memory/active-continuation.md` — **if it exists and is non-empty** (in-flight handoff from another session/tool).
2. `ai-assistant/memory/agent-profile.md`
3. `ai-assistant/memory/project-understanding.md`
4. `ai-assistant/memory/learning-log.md` — **last 1–3 dated entries** (recent decisions and next actions).
5. If the task touches defects or incidents: `ai-assistant/memory/bug-patterns.md`
6. If the task touches GTM/market: `ai-assistant/memory/market-notes.md`

Skip files that do not exist yet; create `learning-log.md` when first needed.

## Write order (before declaring done on non-trivial work)

Persist to disk **before** saying done, complete, or finished:

1. **Default:** Append a new **dated section** to `ai-assistant/memory/learning-log.md` using the template below.
2. **Instead of or in addition to (when appropriate):**
   - `project-understanding.md` / `agent-profile.md` — durable product, stack, or constraint facts changed.
   - `bug-patterns.md` — recurring defect class or near-miss.
   - `market-notes.md` — durable GTM/competitive note.
3. **`active-continuation.md`:** Use only for **unfinished** work that the **next** turn must continue (ephemeral). **Clear or delete** when the continuation is consumed.

If you only updated `project-understanding.md` (and nothing belongs in `learning-log.md`), state that explicitly in the final reply and still mention the path.

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
- **Hygiene:** `bash ./ai-assistant/scripts/learning-doctor.sh` before large maintenance passes when available.
