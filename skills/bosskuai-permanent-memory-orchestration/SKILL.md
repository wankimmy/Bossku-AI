---
name: bosskuai-permanent-memory-orchestration
description: Use when the task touches permanent memory, vector DB sync, cross-tool recall, routing memory hygiene, durable plans, or forgetting past decisions.
---

# bosskuai-permanent-memory-orchestration

## Purpose

Make BosskuAI remember useful context across Claude Code, Cursor, and Codex by writing durable memory files and syncing them into the local SQLite vector index.

Use this skill when the user asks for:

- permanent memory
- vector DB memory
- cross-tool memory
- remembering past conversations across Claude Code, Cursor, and Codex
- auto-saving plans, decisions, learnings, or handoffs
- memory hygiene, retrieval quality, or long-term context issues

## Default flow

For every meaningful task:

1. **Retrieve first**
   - Read `.bossku/memory/handoff.md` if active.
   - Query vector memory before broad file reads:

     ```bash
     python3 ai-assistant/scripts/auto_memory.py query "<task summary>" --limit 5
     ```

2. **Plan with frontier model**
   - Use the strongest available model for task decomposition, risk detection, architecture, and implementation plan.
   - Store durable plans:

     ```bash
     python3 ai-assistant/scripts/auto_memory.py remember --tool <claude|cursor|codex> --kind plan "<compact plan>"
     ```

3. **Execute with lower-cost model**
   - Use the lighter execution model for mechanical edits, local refactors, docs updates, and straightforward implementation.
   - Escalate back to frontier model if the execution step touches payments, auth, privacy, data loss, migrations, security, or repeated failures.

4. **Audit with frontier model**
   - Review the diff, tests, assumptions, and risk areas.
   - Record reusable findings:

     ```bash
     python3 ai-assistant/scripts/auto_memory.py remember --tool <claude|cursor|codex> --kind learning "<outcome, verification, risks, next action>"
     ```

5. **Sync vector DB**
   - After any memory write:

     ```bash
     python3 ai-assistant/scripts/auto_memory.py sync
     ```

## What to store

Store only durable information:

- decisions
- user preferences
- project constraints
- architecture choices
- recurring bug patterns
- verification results
- unfinished handoff state
- product or market direction that should be reused

Do not store:

- secrets, tokens, passwords, private keys
- full logs unless explicitly needed
- temporary one-off chat content
- unverified assumptions as facts

## Memory targets

| Kind | File | Use |
|---|---|---|
| `conversation` | `ai-assistant/memory/conversation-memory.md` | useful cross-tool chat/request history |
| `durable` | `ai-assistant/memory/durable-memory.md` | stable decisions, preferences, constraints |
| `plan` | `.bossku/memory/plans.md` | reusable non-trivial plans |
| `learning` | `.bossku/memory/learnings.md` | outcomes, verification, next actions |
| `bug` | `.bossku/memory/learnings.md` | recurring defects and fixes |
| `market` | `.bossku/memory/project.md` | positioning, competitor, GTM notes |
| `continuation` | `.bossku/memory/handoff.md` | unfinished work only |

Raw events are also appended to `ai-assistant/memory/conversation-log.jsonl` for audit, but the model should retrieve from the vector DB and curated markdown memory first.

## Tool-specific behavior

### Claude Code

When hooks are enabled, `UserPromptSubmit` captures incoming user prompts and `Stop` syncs vector memory. The hook is advisory and local-only.

Enable:

```bash
bash scripts/enable-hooks.sh
```

### Cursor

Cursor project rules cannot reliably force automatic model switching or hook execution in every environment. The rule must still enforce the protocol:

- retrieve memory first
- plan before execution
- save durable plan/outcome via `auto_memory.py remember`
- sync after memory writes

### Codex

Use `.codex/config.toml` and specialist agents:

- planner/reviewer: frontier model
- main executor: lower-cost model
- reviewer/security reviewer before declaring done

## Failure handling

- If vector DB is missing, run `python3 ai-assistant/scripts/auto_memory.py sync`.
- If retrieval returns weak hits, read targeted memory files directly.
- If a memory write fails, state the failure and continue with explicit handoff in the final answer.
- If hook payload has no useful text, do not invent a memory entry.
