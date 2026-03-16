# BosskuAI

A public, reusable agent setup for product-building teams who want one assistant package that can think like:

- a product strategist
- a planner and operator
- a UI/UX expert
- a design-to-code implementer
- a cybersecurity and risk reviewer
- a business-logic flaw hunter
- a bug-finding and code-review specialist
- a software architect
- a source-code analysis expert
- a code revamp and refactor advisor
- a coding best practices reviewer
- a continuation and handoff operator for context-limit cases
- a polyglot engineering advisor
- a market and trends analyst
- a marketing and growth strategist
- an SEO and GEO advisor
- an AI model selection advisor

This repo is tool-agnostic by default and includes ready-to-use guidance for:

- Codex via `AGENTS.md`
- Claude via `CLAUDE.md`
- Claude rules via `.claude/rules/`
- Cursor via `.cursor/rules/`

It also includes local skills, checklists, templates, and memory scaffolding so the assistant can improve over time instead of only reacting per session.

All repo references are relative, so anyone can clone this repo into any directory, rename the root folder, and customize it without rewriting absolute paths.

## What This Agent Is For

Use this repo when you want an AI collaborator that can:

- shape product requirements
- challenge weak assumptions
- review business workflows and edge cases
- turn screens or mockups into implementation-ready guidance
- review security, abuse cases, privacy, and operational risk
- read unfamiliar source code quickly and explain the real architecture
- hunt bugs, regressions, logic flaws, and misuse paths
- study a codebase first, understand its structure, and implement changes in the current style
- apply coding best practices without fighting the existing project conventions
- pause safely when model limits are near, then continue cleanly on retry
- reason across programming languages and frameworks
- analyze competitors, trends, positioning, and market opportunities
- shape launch plans, roadmaps, and execution slices
- think about growth, distribution, SEO, GEO, and marketing strategy
- recommend the right AI model for a specific task, tradeoff, or workflow

## Expert Surfaces Included

- Product strategy and requirement shaping
- Planning, roadmap slicing, and execution strategy
- UI/UX and design systems thinking
- Design-to-code translation
- Cybersecurity and risk review
- Business logic and workflow review
- Bug finding and code review
- Software architecture and system design
- Source-code analysis and repository understanding
- Code revamp and safe refactoring
- Coding best practices and implementation quality
- Context-limit continuation and retry-safe handoff
- Polyglot engineering across languages and frameworks
- Market research and competitive analysis
- Marketing, growth, and go-to-market thinking
- SEO and GEO strategy
- AI model recommendation and task-to-model fit
- Durable learning and promotion into stronger guidance

## Repo Structure

```text
bosskuAI/
├── AGENTS.md
├── CLAUDE.md
├── .claude/rules/
├── .cursor/rules/
└── .codex-assistant/
    ├── skills/
    ├── references/
    └── memory/
```

## How To Use

1. Put this starter at the root of the workspace you actually want the AI to work in.
2. Treat `AGENTS.md` as the source-of-truth instructions for the repo.
3. Use [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) for the actual setup flow after cloning.
4. Let project understanding draft `.codex-assistant/memory/agent-profile.md` and `.codex-assistant/memory/project-understanding.md` from repo evidence, then refine them.

## Start Here

- [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md)
- [examples/sample-prompts.md](examples/sample-prompts.md)
- [examples/sample-agent-profile.md](examples/sample-agent-profile.md)

## Customization

This starter is meant to be remixed.

- Rename the root folder if you want. The internal references are relative.
- Edit `AGENTS.md` to change posture, priorities, or expert surfaces.
- Fill in `.codex-assistant/memory/agent-profile.md` with your domain, product, audience, and constraints, or ask project understanding to draft it dynamically first.
- Use `examples/sample-agent-profile.md` as a starting point if you want a filled example first.
- Add or remove skills under `.codex-assistant/skills/`.
- Extend playbooks and checklists under `.codex-assistant/references/`.

## Principles

- Research first, then decide
- Push for clarity over vague ambition
- Prefer the smallest real step that reduces risk
- Study the existing structure first, then implement in the current style when possible
- Apply coding best practices in a way that fits the current project, not generic textbook rules
- Treat business logic as seriously as code
- Treat security, privacy, and abuse cases as first-class concerns
- Treat UI fidelity and usability as part of correctness
- Treat planning and execution sequencing as part of strategy, not admin work
- Treat distribution, marketing, SEO, and GEO as part of product success
- Start in a planning-first mode for meaningful tasks before implementation or strong conclusions
- Recommend the best-fit AI model profile before execution starts on meaningful tasks
- Stop cleanly and preserve continuation context if model limits are likely to cut the task mid-process
- Verify market and trend claims with current sources when they matter

## Suggested Use Cases

- "Review this PRD and turn it into implementation slices"
- "Challenge this SaaS idea and tell me what is weak"
- "Turn this Figma/screenshot into a delivery-ready engineering spec"
- "Review this signup flow for abuse, fraud, and logic flaws"
- "Research competitors and recommend a positioning strategy"
- "Audit this feature for product, UX, security, and operational risk"
- "Plan a 90-day launch roadmap for this product"
- "Give me an SEO and GEO content strategy for this SaaS"
- "Design a go-to-market motion for this niche"
- "Read this codebase and explain the real architecture"
- "Find likely bugs and logic flaws before we ship"
- "Which AI model should I use for this exact task and why"

## License

This repo currently uses the MIT License. Review it before publishing if you want a different licensing model.
