---
name: bossku-ai
description: Use when a Codex workspace needs BosskuAI routing, repo-local memory, skill selection, or quality gates without loading the full toolkit.
---

# BosskuAI

Use this package as the Codex-facing entrypoint for BosskuAI.

## Workflow

1. Inspect the local repo before choosing a specialist skill.
2. Read the repo memory files only when they are relevant to the task.
3. Route to focused BosskuAI skills in `ai-assistant/skills/` when the task clearly needs domain playbooks.
4. Keep output direct, human, and grounded in files or command results.
5. Verify changes with the narrowest useful command before reporting completion.

## Routing

- For code work, prioritize engineering, testing, review, architecture, and framework-specific skills.
- For product or growth work, use product strategy, market, SEO/GEO, sales, or content skills.
- For uncertainty, start with repo inspection and summarize the tradeoff before loading deeper references.

## Boundaries

- Do not load every BosskuAI playbook by default.
- Do not overwrite project memory unless the user asks for a memory update.
- Treat tests, browser checks, and command output as stronger evidence than a skill instruction.
