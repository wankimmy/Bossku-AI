# Always-on model router

## Goal

BosskuAI should use expensive reasoning where it matters and cheaper execution where it is safe:

1. **Frontier model plans.**
2. **Lower-cost model executes.**
3. **Frontier model audits.**
4. **Durable memory is saved and synced into vector DB.**

This keeps quality high without forcing every token through the most expensive model.

## Default phase policy

| Phase | Model class | Work |
|---|---|---|
| Memory retrieve | current/tool model | query vector DB, read targeted files |
| Plan | frontier | requirements, architecture, risks, testing, task split |
| Execute | lower-cost capable model | concrete edits, docs, refactors, mechanical implementation |
| Audit | frontier | correctness, security, business logic, test gaps, final decision |
| Memory save | current/tool model | write durable summary and sync vector DB |

## Escalation rules

Use the frontier model for execution too when any of these are true:

- auth, authorization, sessions, tokens, or secrets
- payment, billing, subscription, settlement, refund, or financial data
- privacy, PII, data retention, user safety
- destructive migrations, data loss risk, queue/idempotency concerns
- multi-service architecture or deployment changes
- security-sensitive integrations and webhooks
- repeated failed attempts by the lower-cost model
- unclear requirements with high cost of being wrong

## Cross-tool defaults

| Tool | Plan | Execute | Audit |
|---|---|---|---|
| Claude Code | `claude-opus-4-7` | `claude-sonnet-4-6` | `claude-opus-4-7` |
| Codex | `gpt-5.4` planner agent | `gpt-5.4-mini` main agent | reviewer/security reviewer with high reasoning |
| Cursor | strongest available model | lower-cost available model | strongest available model |

Cursor and Codex model switching depends on what the local tool exposes. BosskuAI enforces the protocol through rules/config; it cannot override a tool UI that does not expose automatic model switching.

## Required memory calls

Before meaningful work:

```bash
python3 ai-assistant/scripts/auto_memory.py query "<task summary>" --limit 5
```

After durable plan:

```bash
python3 ai-assistant/scripts/auto_memory.py remember --tool <claude|cursor|codex> --kind plan "<compact plan>"
```

After durable outcome:

```bash
python3 ai-assistant/scripts/auto_memory.py remember --tool <claude|cursor|codex> --kind learning "<outcome, verification, risks, next action>"
```

Manual durable memory:

```bash
python3 ai-assistant/scripts/auto_memory.py remember --tool manual --kind durable "Decision: <stable decision>. Reason: <why>."
```

Status:

```bash
python3 ai-assistant/scripts/auto_memory.py status
```

## Done definition

A meaningful task is done only when:

- plan was created or intentionally skipped as trivial
- execution matches the plan or the plan was updated
- audit completed with explicit risks/checks
- tests/checks were run or skipped with reason
- durable memory was written when useful
- vector DB was synced after memory writes
