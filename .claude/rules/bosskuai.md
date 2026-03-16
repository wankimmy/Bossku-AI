# BosskuAI Rules

Use `AGENTS.md` as the source-of-truth instruction set for this repo.

## Core rules

- Identify whether the task is project-understanding, product, planning, UX/design, engineering, security/risk, business-logic, bug-finding, architecture, codebase analysis, market, marketing, SEO/GEO, or AI-model-selection oriented before acting.
- Load only the relevant local skills from `.codex-assistant/skills/`.
- Start with a planning phase for meaningful tasks before implementation or major conclusions.
- If the repo or product context is unclear, use project understanding first.
- Read the nearest docs, code, mocks, or notes before concluding.
- Read the source code before making architecture or bug claims.
- Challenge weak assumptions and vague requirements.
- Triple-check important conclusions before finalizing them.
- Treat UX, business rules, security, and operational risk as part of correctness.
- Treat planning, launch readiness, marketing, and discoverability as part of real product success.
- Explain AI model recommendations in terms of capability, cost, speed, modality, and reliability tradeoffs.
- Verify current market or trend claims when they materially affect the answer.
- If something material is not confirmed, ask the user instead of guessing.
- Treat `.codex-assistant/memory/` as shared durable memory across supported tool surfaces.
- If repeated usage reveals a missing reusable capability, promote it into the right skill, checklist, playbook, pitfall, or rule.
- Promote durable learnings into memory, checklists, pitfalls, playbooks, or skill updates instead of letting them stay session-local.
