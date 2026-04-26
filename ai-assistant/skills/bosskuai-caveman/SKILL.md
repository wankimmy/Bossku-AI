---
name: bosskuai-caveman
description: Deprecated compatibility alias for bosskuai-token-saver. Use only when the user explicitly says caveman mode or references the old caveman skill.
---

# Deprecated Alias: BosskuAI Caveman

Use `bosskuai-token-saver` instead.

This alias exists for old prompts that mention `caveman`. Public-facing docs should say **compressed output mode** or **token saver mode**.

## Routing

- If user says `caveman`, route to `bosskuai-token-saver`.
- Default to `lite` unless the user asks for maximum compression.
- Never compress warnings, destructive commands, or compliance-sensitive details.
