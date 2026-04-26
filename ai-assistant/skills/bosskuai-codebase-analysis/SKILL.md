---
name: bosskuai-codebase-analysis
description: Use this for tracing execution paths, mapping call chains and module boundaries, identifying side effects, and summarizing how code actually behaves at runtime. Use bosskuai-project-understanding first when the project purpose and stack are still unknown.
---

# BosskuAI Codebase Analysis

Use this for tracing execution paths, mapping call chains and module boundaries, identifying side effects, and summarizing how code actually behaves at runtime. Use bosskuai-project-understanding first when the project purpose and stack are still unknown.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-codebase-analysis-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

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
