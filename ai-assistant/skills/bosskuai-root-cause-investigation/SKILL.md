---
name: bosskuai-root-cause-investigation
description: Deprecated alias skill. Use this only to route to `bosskuai-bug-finding`, which now includes deep root-cause investigation workflows with runtime evidence.
---

# BosskuAI Root Cause Investigation (Merged)

This skill has been merged into **`bosskuai-bug-finding`**.

## What to do when this skill is requested

1. Load `bosskuai-bug-finding` as the primary skill.
2. Run **Deep investigation mode** in that skill (logs, DB rows, queues, webhooks, runtime timeline correlation).
3. Keep `bosskuai-business-logic-review` as a secondary skill when invariant or workflow semantics are likely involved.
4. Load `bosskuai-cybersecurity-risk` when the incident touches auth, fraud, privacy, or sensitive data boundaries.

## Canonical location

Use `ai-assistant/skills/bosskuai-bug-finding/SKILL.md` as the source-of-truth for root-cause and incident investigation.
