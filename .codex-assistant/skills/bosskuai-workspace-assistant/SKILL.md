---
name: bosskuai-workspace-assistant
description: Use this for general work inside the BosskuAI repo or when a task spans multiple expert surfaces such as product, planning, UI/UX, bug-finding, software architecture, source-code analysis, security, business logic, marketing, and market analysis.
---

# BosskuAI Workspace Assistant

Use this skill to orchestrate work across the repo.

## Workflow

1. Classify the task:
   - project understanding
   - product strategy
   - planning and execution
   - UI/UX or design-to-code
   - bug finding
   - software architecture
   - codebase analysis
   - code revamp
   - coding best practices
   - polyglot engineering
   - cybersecurity and risk
   - business-logic review
   - market analysis
   - marketing and growth
   - SEO/GEO
   - AI model selection
   - mixed
2. For meaningful tasks, start in plan mode before execution.
3. Recommend the most suitable AI model profile for the task before execution starts, with a short tradeoff note and fallback if useful.
4. Load only the relevant expert skills.
5. If the repo or product context is unclear, use project understanding first.
6. Read the nearest docs, mocks, code, or specs.
7. Study the current code structure, conventions, and extension points before proposing or implementing changes.
8. Apply coding best practices by default, but fit them to the current project conventions and stack.
9. Prefer the smallest safe change that fits the current architecture, and use code revamp only when a broader restructure is clearly justified.
10. Distinguish explicit facts from inference.
11. Triple-check important conclusions before finalizing.
12. If something material is still unconfirmed, ask the user instead of guessing.
13. If repeated usage reveals a missing reusable capability, create or update the right skill, checklist, playbook, pitfall, or rule.
14. After the task, decide whether any durable learning should be promoted.

## References

- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/session-handoff-template.md`
- `../../memory/learning-log.md`
- `../../memory/bug-patterns.md`
- `../../memory/market-notes.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
