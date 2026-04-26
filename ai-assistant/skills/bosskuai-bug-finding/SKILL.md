---
name: bosskuai-bug-finding
description: Use this for bug hunts, regression analysis, suspicious changes, failure-path review, finding likely defects before shipping, and deep incident investigation using logs, DB state, queues, and runtime evidence.
---

# BosskuAI Bug Finding

Use this for bug hunts, regression analysis, suspicious changes, failure-path review, finding likely defects before shipping, and deep incident investigation using logs, DB state, queues, and runtime evidence.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-bug-finding-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

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
