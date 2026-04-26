---
name: bosskuai-rigorous-code-review
description: Skeptical expert code review that maps changes to repo structure and infrastructure, applies strict engineering standards, and prefers minimal fixes. Use for PR or pre-merge review, harsh or adversarial review requests, challenging an implementation, or when the user wants strict best practices without unnecessary refactors.
---

# BosskuAI Rigorous Code Review

Skeptical expert code review that maps changes to repo structure and infrastructure, applies strict engineering standards, and prefers minimal fixes. Use for PR or pre-merge review, harsh or adversarial review requests, challenging an implementation, or when the user wants strict best practices without unnecessary refactors.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-rigorous-code-review-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

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
