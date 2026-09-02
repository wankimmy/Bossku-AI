# Memory-first handoff protocol (cross-tool)

**Applies to:** Claude Code, Cursor, Codex, OpenCode, OMP, and any surface using BosskuAI rules.

**Purpose:** Chat history is not shared between tools or sessions; **files are.** `.bossku/memory/` is the coordination layer. Full contract: root `AGENTS.md` § Memory and `docs/memory.md`.

## Files

| File | Written by | Holds |
|---|---|---|
| `handoff.md` | the agent, by hand | unfinished work only; cleared when consumed; never exported |
| `plans.md` | `bossku remember --kind plan` | compact pre-execution plans worth continuing |
| `decisions.md` | `bossku remember --kind decision` | choices and the reason |
| `learnings.md` | `bossku remember --kind learning` | verified outcomes, recurring bugs, verification results |
| `project.md` | `bossku remember --kind project` | durable repo facts |

`bossku remember` redacts secrets and exports the four kind files to `<vault>/BosskuAI/<project>/` when an Obsidian vault is configured. `bossku sync --project .` re-exports after manual edits.

## Read order (before substantive edits or repo-specific claims)

1. `handoff.md` — if non-empty, an in-flight handoff from another session or tool.
2. `project.md` — orientation.
3. Only when the task needs them: `decisions.md` (before proposing a change of direction), `plans.md` (when continuing work), `learnings.md` (last few entries, or the area being touched).

Skip files that do not exist; `bossku init <project>` seeds them.

## Write order (before declaring done on non-trivial work)

1. **Before execution**, when the task touches multiple files or phases, contains decisions likely to matter later, or another session may continue it: `bossku remember --project . --kind plan "<goal / approach / expected files / risks>"`.
2. **After execution**: `--kind learning` for outcomes and verification, `--kind decision` for choices, `--kind project` when durable facts changed. One entry each; a few lines; link paths rather than pasting content.
3. **Unfinished work**: overwrite `handoff.md` with the template below. Delete or clear it when the continuation is consumed.
4. State the export result (`ok` / `skipped` / `pending`) if the user cares about Obsidian.

## Trivial exception

No memory write when **all** are true: single-line or single-hunk fix, or pure factual lookup, or yes/no with no repo edits; **and** no durable product, architecture, or constraint changed. Stay silent about it unless the user asks for memory status.

## `handoff.md` template

```markdown
# Handoff — YYYY-MM-DD — [Claude Code | Cursor | Codex | other]

- **Goal:**
- **Done:**
- **Not done / blocked:**
- **Decisions made (and why):**
- **Files / artifacts:** [paths, PR or issue URLs]
- **Verification:** [passed / not run / manual steps]
- **Risks / unknowns:**
- **Suggested skills:** [ids]

FOR_NEXT_MODEL
Next (ordered):
  1.
  2.
```

## Relation to other artifacts

- Session narrative: [`session-handoff-template.md`](session-handoff-template.md) — optional richer structure for the same file.
- Promotion triage: [`checklists/learning-promotion-checklist.md`](checklists/learning-promotion-checklist.md) after logging.
- Behavior rules, not memory: `bosskuai-rules-distill`; skills: `bosskuai-skill-creator`.
