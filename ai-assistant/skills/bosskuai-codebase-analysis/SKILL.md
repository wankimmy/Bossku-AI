---
name: bosskuai-codebase-analysis
description: Use this for reading unfamiliar source code, understanding repository structure, mapping execution paths, explaining architecture, and summarizing how the code actually behaves.
---

# BosskuAI Codebase Analysis

Use this skill when the first task is understanding the codebase correctly.

## Workflow

1. Identify the stack, entry points, top-level structure, dominant code conventions, and visible quality patterns.
2. Follow the real execution path for the behavior in question.
3. Distinguish source-of-truth code from generated or support files.
4. Identify the safest extension points and how the current codebase expects changes to be made.
5. Note the code-quality expectations implied by the repo, such as naming, separation of concerns, error handling, and testing style.
6. Confirm explanations against source code and tests.
7. Summarize clearly what is explicit and what is inferred.

## Output expectation

- stack and repo summary
- execution path
- architecture explanation
- key conventions
- safest extension points
- code-quality expectations
- open uncertainties

## References

- `../../references/playbooks/codebase-analysis-playbook.md`
- `../../references/checklists/codebase-analysis-checklist.md`
