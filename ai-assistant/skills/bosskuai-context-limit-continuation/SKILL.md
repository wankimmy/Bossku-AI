---
name: bosskuai-context-limit-continuation
description: Use this when a task risks hitting model context or token limits mid-process. Stop cleanly, summarize current progress, ask the user to retry or continue in a fresh prompt, and provide a compact continuation state.
---

# BosskuAI Context-Limit Continuation

Use this skill when a task is long enough that the current model session may run out of context budget, hit token limits, or otherwise risk being cut off before completion.

## Workflow

1. Watch for signs that the task is becoming too large for the current model context or reply budget.
2. Stop before truncation instead of letting the work end abruptly.
3. Summarize what has already been completed, what remains, and any key decisions already made.
4. Provide a compact continuation state the next turn can use immediately.
5. Ask the user to retry, continue, or start a fresh prompt so the work can resume cleanly.
6. If files were changed, name them clearly so the next turn can verify the current state quickly.

## Output expectation

- what is completed
- what remains
- key files or artifacts touched
- important risks or open questions
- short retry / continue instruction for the user

## References

- `../../references/session-handoff-template.md`
- `../../references/checklists/context-limit-continuation-checklist.md`
