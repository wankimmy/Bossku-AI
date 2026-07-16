---
name: bosskuai-token-saver
description: Use this when the user explicitly asks for terse output, lower token use, caveman mode, compressed prompts, shorter rules, or reducing prompt bloat without losing meaning.
---

# BosskuAI Token Saver

Use this skill to reduce output or instruction bloat while keeping technical meaning intact.
Public-facing name: **compressed output mode**.
Compatibility alias: `bosskuai-caveman`.

## Modes

- `lite`: concise full sentences. Best default for professional work.
- `full`: fragments allowed. Good for debugging, code review, and terminal-style notes.
- `ultra`: abbreviations and arrows. Use only when the user explicitly wants maximum compression.

## Compression Rules

Remove:

- greetings, apologies, filler, hedging
- repeated context already known
- generic motivational language
- long summaries before the answer
- protocol chatter unless useful to the user

Keep:

- exact commands
- file paths
- warnings for destructive actions
- security, payment, legal, or data-loss caveats
- verification status and unknowns

## Prompt-Bloat Rules

When compressing repo instructions:

1. Keep mandatory boundaries and safety rules intact.
2. Turn long philosophy into operational checks.
3. Move examples into references when they are not needed on every run.
4. Prefer one routing rule over many overlapping specialist rules.
5. Preserve aliases for old prompts.

## Do Not Compress

- destructive operation confirmations
- legal/compliance wording where precision matters
- security warnings
- migration steps where order matters
- user-facing copy being edited for tone, unless requested

## Output Shape

```txt
Mode: lite/full/ultra
Result: ...
Notes: only if meaning changed or risk remains
```

## References

- `../../references/checklists/token-saver-checklist.md`
