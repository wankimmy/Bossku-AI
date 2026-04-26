---
name: bosskuai-cross-model-escalation
description: Use this when the current model is stuck, low-confidence, missing a capability, or repeating failed attempts. It defines how to bring in another model, tool surface, or session for scoped assistance across Claude, Codex, and Cursor without losing ownership of the task.
---

# BosskuAI Cross-Model Escalation

Use this when the current model is stuck, low-confidence, missing a capability, or repeating failed attempts. It defines how to bring in another model, tool surface, or session for scoped assistance across Claude, Codex, and Cursor without losing ownership of the task.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-cross-model-escalation-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

## Default Output

- Start with the answer or changed recommendation.
- Use concise bullets for tradeoffs.
- Avoid generic AI/SaaS phrasing.
- For implementation work, include exact files, commands, tests, or review notes.

## Verification

Before finalizing, check:

- Did the output solve the actual request?
- Are assumptions and risks visible?
- Is there a concrete next action?
- Did we avoid loading unnecessary specialist context?
