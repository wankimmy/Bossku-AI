# Readable memory format

Humans should scan memory without JSON noise. Use **two views** of the same record (`schema.md`).

## Card view (default in UI)

```markdown
### memory_001 · architecture_decision · high
**Project:** bossku-ai  
**Updated:** 2026-05-08  
**Tags:** model-routing, architecture  

**Summary**  
Use Kimi K2.6 as default executor and GPT-5.5 as orchestrator.

**Source** user_instruction
```

## Table view (dashboards)

| ID | Type | Importance | Summary | Updated |
|---|---|---|---|---|
| memory_001 | architecture_decision | high | Use Kimi K2.6 as default executor… | 2026-05-08 |

## Raw JSON

Power users / export — see `schema.md`.

## Export bundle

For portability:

```text
memory-export/
  index.jsonl    # one JSON object per line
  cards.md       # rendered cards for humans
```

Keep exports **small** and **redacted**.
