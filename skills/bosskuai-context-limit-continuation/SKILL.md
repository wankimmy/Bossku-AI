---
name: bosskuai-context-limit-continuation
description: "Use when context/token/quota limits threaten mid-task — write progress to ai-assistant/memory/active-continuation.md, pick the completion model with bosskuai-ai-model-selection, and tell the user to resume in a fresh session."
---

# BosskuAI Context-Limit Continuation

Use this when a task risks hitting model context, token limits, or tight usage/quota mid-process. Stop cleanly, write progress into ai-assistant/memory/active-continuation.md, pair with bosskuai-ai-model-selection to recommend which model should complete the remaining work, and tell the user explicitly to open a fresh chat/session with that model. Also contains token-budget guidance for keeping agent sessions efficient.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-context-limit-continuation-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

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
