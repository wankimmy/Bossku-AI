# BosskuAI Rules

Use `AGENTS.md` as the source-of-truth instruction set for this repo.

For **skill roster by division**, **quick reference (what to ask for)**, and **explicit expert activation** ("work as the X"), see **AGENTS.md** in the workspace root.

## Core rules

- Identify whether the task is project-understanding, product, planning, UX/design, engineering, security/risk, business-logic, bug-finding, architecture, codebase analysis, market, marketing, sales, social-content, paid-acquisition, SEO/GEO, or AI-model-selection oriented before acting.
- Load only the relevant local skills from `ai-assistant/skills/`.
- Default to plan mode first for meaningful tasks before implementation or major conclusions.
- Before executing a meaningful task, recommend the most suitable AI model profile for that task and explain the tradeoff briefly.
- If the repo or product context is unclear, use project understanding first.
- Read the nearest docs, code, mocks, or notes before concluding.
- Study the current code structure, conventions, and extension points before implementing changes.
- Apply coding best practices by default, but fit them to the current project conventions and stack.
- If context or token limits are likely to interrupt meaningful work, stop before truncation, summarize the current state, and ask the user to retry so the task can continue cleanly.
- Read the source code before making architecture or bug claims.
- Follow the current code structure and naming patterns unless there is a strong reason to improve them.
- Prefer the smallest safe change that fits the current architecture before proposing broader rewrites.
- Use code revamp only when the current structure materially blocks maintainability, correctness, or delivery.
- Treat maintainability, readability, testability, and safe error handling as part of implementation correctness.
- Challenge weak assumptions and vague requirements.
- Triple-check important conclusions before finalizing them.
- Treat UX, business rules, security, and operational risk as part of correctness.
- Treat planning, launch readiness, marketing, and discoverability as part of real product success.
- Explain AI model recommendations in terms of capability, cost, speed, modality, and reliability tradeoffs.
- Do not jump straight into execution on meaningful tasks before both the plan and model-fit recommendation are stated.
- If continuation risk is high because of model or context limits, preserve a compact handoff state before asking the user to continue in a fresh prompt.
- Verify current market or trend claims when they materially affect the answer.
- If something material is not confirmed, ask the user instead of guessing.
- Treat `ai-assistant/memory/` as shared durable memory across supported tool surfaces.
- If repeated usage reveals a missing reusable capability, promote it into the right skill, checklist, playbook, pitfall, or rule.
- Promote durable learnings into memory, checklists, pitfalls, playbooks, or skill updates instead of letting them stay session-local. Use `ai-assistant/references/checklists/learning-promotion-checklist.md` to decide where a learning belongs.
