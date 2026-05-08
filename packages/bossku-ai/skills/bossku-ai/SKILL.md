---
name: bossku-ai
description: Use when a Codex workspace needs BosskuAI routing, repo-local memory, skill selection, or quality gates without loading the full toolkit.
---

# BosskuAI

Use this package as the Codex-facing entrypoint for BosskuAI.

## Mandatory indicator

Every response must begin with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

After installing BosskuAI into the workspace repo root: see `agents/skill-detector.md`.

## Workflow

1. Inspect the local repo before choosing a specialist skill.
2. Decide if memory retrieval helps; query vector memory only when relevant.
3. Route to focused BosskuAI skills in `ai-assistant/skills/` when the task clearly needs domain playbooks.
4. Orchestrate/plan before large edits; audit after substantive code changes.
5. Keep output direct, human, and grounded in files or command results.
6. Verify changes with the narrowest useful command before reporting completion.

## Routing

- For code work, prioritize engineering, testing, review, architecture, and framework-specific skills.
- For product or growth work, use product strategy, market, SEO/GEO, sales, or content skills.
- For uncertainty, start with repo inspection and summarize the tradeoff before loading deeper references.
- Full routing table and fallbacks: `agents/model-router.md` at repo root.

## Boundaries

- Do not load every BosskuAI playbook by default.
- Do not overwrite project memory unless the user asks for a memory update.
- Treat tests, browser checks, and command output as stronger evidence than a skill instruction.
