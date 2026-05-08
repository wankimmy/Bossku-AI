# Memory summarizer

Summaries keep prompts **cheap** and memory **useful**.

## When to summarize

- Before injecting session history into a new agent turn
- Before promoting inbox candidates (`scripts/bosskuai memory approve`)
- After long audits — capture **decisions** and **risks**, not play-by-play

## Format (machine-friendly)

```text
DECISION: <one line>
CONTEXT: <why / constraints>
EVIDENCE: <files, tests, links>
RISK: <low|medium|high> — <note>
NEXT: <one action>
```

## Format (human card)

```text
**What we decided**
- ...

**Why**
- ...

**Proof / links**
- ...

**Open risks**
- ...
```

## Summarization rules

- Remove names of secrets; refer to them as `REDACTED` or “env var *name*”.
- Merge duplicate bullets.
- Lowercase tags for stable search.

## Tools

- `python3 ai-assistant/scripts/auto_memory.py` — query/remember/sync
- `scripts/bosskuai memory extract|inbox|approve` — promotion loop
