---
name: bosskuai-workspace-assistant
description: Use this for general work in a workspace using this starter, or when a task spans multiple expert surfaces such as product, planning, UI/UX, bug-finding, software architecture, source-code analysis, security, business logic, marketing, and market analysis.
---

# BosskuAI Workspace Assistant

Use this skill to orchestrate work across the current workspace.

## Workflow

1. Classify the task:
   - project understanding
   - product strategy
   - planning and execution
   - project management
   - launch commercialization
   - engineering delivery
   - UI/UX or design-to-code
   - bug finding
   - software architecture
   - codebase analysis
   - code revamp
   - coding best practices
   - context-limit continuation
   - polyglot engineering
   - cybersecurity and risk
   - agent security hardening
   - business-logic review
   - market analysis
   - marketing and growth
   - social content calendar
   - paid acquisition and monetization
   - sales strategy
   - SEO/GEO
   - AI model selection
   - mixed
2. For meaningful tasks, start in plan mode before execution.
3. Recommend the most suitable AI model for the task before execution starts, ideally by concrete model name available in the current tool, with a short tradeoff note and fallback if useful.
4. Load only the relevant expert skills.
5. If the repo or product context is unclear, or `../../memory/agent-profile.md` is still sparse, use project understanding first.
6. Read the nearest docs, mocks, code, or specs.
7. Study the current code structure, conventions, and extension points before proposing or implementing changes.
8. Apply coding best practices by default, but fit them to the current project conventions and stack.
9. For meaningful engineering work, prefer the engineering-delivery workflow: plan, test-guide, implement, review, and verify.
10. For AI-agent workspace risk, use agent-security-hardening rather than treating it as ordinary application security.
11. If context or token limits are likely to interrupt the task, switch into context-limit continuation behavior before truncation.
12. Prefer the smallest safe change that fits the current architecture, and use code revamp only when a broader restructure is clearly justified.
13. Distinguish explicit facts from inference.
14. Triple-check important conclusions before finalizing.
15. If something material is still unconfirmed, ask the user instead of guessing.
16. If repeated usage reveals a missing reusable capability, create or update the right skill, checklist, playbook, pitfall, or rule.
17. After the task, decide whether any durable learning should be promoted.
18. When project understanding runs early, prefer drafting both `../../memory/agent-profile.md` and `../../memory/project-understanding.md` before narrower expert work begins.

## References

- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/session-handoff-template.md`
- `../../memory/learning-log.md`
- `../../memory/bug-patterns.md`
- `../../memory/market-notes.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
