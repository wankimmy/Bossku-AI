---
name: bosskuai-workspace-assistant
description: Use this for general work in a workspace using this starter, or when a task spans multiple expert surfaces and needs routing to the right local skills with minimal context overhead.
---

# BosskuAI Workspace Assistant

Use this skill as the orchestration layer when the task is cross-cutting, the domain is unclear, or repo context is not established yet.

## Load this skill when

- the task spans multiple domains
- the closest expert skill is not obvious yet
- the repo/product context is still unclear

If the domain is already clear, load the expert skill directly instead.

## Core workflow

1. Use root `AGENTS.md` quick reference to choose 1 primary skill and at most 1 secondary skill.
2. Read `ai-assistant/memory/active-continuation.md` first. If `semantic-memory.sqlite3` exists, query it before opening broad memory files.
3. Read nearby code, docs, manifests, and tests before concluding anything repo-specific.
4. For non-trivial tasks, if the plan is likely to matter later, store a compact pre-execution entry in `ai-assistant/memory/plan-log.md` and sync vector memory before execution.
5. Prefer the smallest safe change that fits the current architecture.
6. Run the loaded skill workflow instead of inventing a new one.
7. Before finishing, verify the work and decide whether memory, checklist, pitfall, playbook, or skill promotion is warranted.

## Routing heuristics

- unclear repo or product context -> `bosskuai-project-understanding`
- implementation or refactor -> `bosskuai-engineering-delivery`
- code review, risk review, or bug hunt -> quality/security skills
- architecture, schema, or contract decisions -> architecture skills
- launch, growth, market, or GTM work -> growth/product skills
- context pressure or large scope -> `bosskuai-context-limit-continuation` or `bosskuai-subagent-delegation`

## Token discipline

- Do not load skills speculatively.
- Prefer targeted reads over full-file dumps.
- Tool-specific rules should point back to root `AGENTS.md`, not restate its full routing tables.
- If a task is getting large, checkpoint before context gets tight.

## Done gate

Do not declare done until:

- the requested change is applied to disk when edits were required
- the relevant verification or testing pass has been attempted
- unverified gaps are stated explicitly
- durable learnings are either captured or intentionally deferred

## References

- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/checklists/continuous-learning-checklist.md`
- `../../references/session-handoff-template.md`
- `../../memory/plan-log.md`
- `../../memory/learning-log.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
