---
name: bosskuai-subagent-delegation
description: Use this to automatically delegate heavy, parallelizable, or risky tasks to subagents instead of running them serially in the main session. Covers auto-trigger decision logic and tool-specific patterns for Claude Code (Agent tool + worktree isolation), Cursor (parallel Composer tabs), and Codex (parallel runs).
---

# BosskuAI Subagent Delegation

Use this to automatically delegate heavy, parallelizable, or risky tasks to subagents instead of running them serially in the main session. Covers auto-trigger decision logic and tool-specific patterns for Claude Code (Agent tool + worktree isolation), Cursor (parallel Composer tabs), and Codex (parallel runs).

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-subagent-delegation-playbook.md` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

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
