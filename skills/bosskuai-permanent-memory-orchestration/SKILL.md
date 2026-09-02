---
name: bosskuai-permanent-memory-orchestration
description: "Use when the task touches BosskuAI project memory (.bossku/memory), bossku remember or bossku sync, Obsidian export, cross-tool recall between Claude Code, Cursor and Codex, memory hygiene, durable decisions or plans, or the agent forgetting past decisions."
---

# BosskuAI Permanent Memory Orchestration

Make BosskuAI remember useful context across Claude Code, Cursor, Codex, OpenCode, and OMP by writing curated Markdown memory that every tool can read, and letting the CLI export it to Obsidian.

## How this differs from nearby skills

- **`bosskuai-continuous-learning`**: decides *whether* a lesson is durable and *which* artifact should hold it; this skill owns the memory mechanics once the answer is "memory".
- **`bosskuai-handoff`** / **`bosskuai-context-limit-continuation`**: write the ephemeral `handoff.md` bridge; this skill covers the durable files.
- **`bosskuai-claude-md-management`** / **`bosskuai-rules-distill`**: instruction and rule files; memory is facts and decisions, not behavior rules.

## The real memory system (BosskuAI 2.x)

There is no vector database, no hook-driven capture, and no `ai-assistant/` tree. Memory is plain Markdown under `.bossku/memory/` in the project, written through the CLI so redaction and export run every time.

| Kind | File | Store here |
|---|---|---|
| `project` | `.bossku/memory/project.md` | what the repo is, stack, constraints, source-of-truth files |
| `decision` | `.bossku/memory/decisions.md` | choices worth defending later, with the reason |
| `plan` | `.bossku/memory/plans.md` | compact plans another session may continue |
| `learning` | `.bossku/memory/learnings.md` | verified outcomes, recurring bugs, verification results |
| (manual) | `.bossku/memory/handoff.md` | unfinished work only; clear when done; never exported |

```bash
bossku remember --project . --kind decision "Use toolkit-only main; runtime moved to the CLI."
bossku remember --project . --kind learning "Pest needs 2G memory on the fixed-tenant schema; see docs/testing.md."
bossku sync --project .        # re-export after manual edits or when the vault was offline
```

`bossku remember` appends a UTC-stamped section, redacts secrets, and immediately runs the Obsidian export when a vault is configured. `bossku init <project>` seeds the five template files.

## Obsidian export (auto-sync)

- **One-way**: repo → `<vault>/BosskuAI/<project-name>/`. Never write into the vault directly and never treat vault edits as the source; if a vault file was edited by hand, the next export writes a `*.conflict.md` copy beside it instead of overwriting.
- **Vault path** lives only in `~/.bosskuai/config.json` (`obsidian_vault`, set by `bossku install --vault <path>`). If it is missing, `remember` reports `vault: skipped` and memory still saves locally.
- **Never exported**: `handoff.md`, instincts, raw prompts, transcripts, logs.
- Host workspaces may run their own vault syncs (hooks that mirror other memory folders or session logs). Those are outside BosskuAI; keep writing through `bossku remember` so the curated export stays consistent, and do not duplicate the same note into two systems.

## Default flow

1. **Retrieve first.** Read `.bossku/memory/handoff.md` if it is non-empty, then only the kind files the task needs (`project.md` for orientation, `decisions.md` before proposing a change of direction, `plans.md` when continuing work, `learnings.md` before debugging a known area). Do not dump the whole directory.
2. **Plan, then store the plan** when it spans multiple files or phases or another session may pick it up: `bossku remember --kind plan`.
3. **Execute.**
4. **Record outcomes** a future session would otherwise relearn: `--kind decision` for choices, `--kind learning` for verified lessons, `--kind project` when durable facts about the repo changed.
5. **Confirm the export** from the command's `vault` result (`ok`, `skipped`, or `pending`) and state it in the reply when the user cares about Obsidian.

## Memory hygiene

- Fix stale or contradictory entries when you find them; append a dated correction rather than silently rewriting history.
- Keep entries to a few lines. Link to files, PRs, or docs instead of pasting them.
- Do not store secrets, tokens, `.env` contents, full logs, customer data, one-off chatter, or unverified assumptions presented as facts. `bossku/redact.py` catches common token shapes; it is a backstop, not permission.
- `handoff.md` is ephemeral: clear it when the work completes.

## Failure handling

- `vault: pending` (vault folder unavailable): local memory is saved; run `bossku sync --project .` later.
- `vault: skipped` (no vault configured): tell the user how to set it (`bossku install --vault <path>`); do not invent an export.
- Missing `.bossku/memory/`: run `bossku init <project>` or let `remember` create it.
- If a write fails, say so and put the continuation state in the reply.

## Output

- memory files read
- entries written (kind + one-line summary)
- export status (`ok` / `skipped` / `pending`, plus conflict files if any)
- stale memory corrected or flagged

## References

- `../../docs/memory.md`
- `../../bossku/memory.py`
- `../../references/memory-first-handoff-protocol.md`
