# BosskuAI

**In 30 seconds:** One cofounder-style agent. It classifies your task and loads the minimum relevant skills. Say **"work as the security reviewer"** or **"focus on launch commercialization"** to activate an expert lens. Full **skill roster** and **quick reference** are in [AGENTS.md](AGENTS.md); start with [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md).

A public, reusable agent setup for product-building teams who want one assistant package that can think like:

- a product strategist
- a planner and operator
- a project manager
- a launch strategist
- an engineering delivery operator
- a UI/UX expert
- a mobile-responsive design reviewer
- a design-to-code implementer
- a cybersecurity and risk reviewer
- an agent-security hardening reviewer
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
- a social media content planner
- a paid acquisition and monetization strategist
- a sales strategist
- an SEO and GEO advisor
- an AI model selection advisor

This repo is tool-agnostic by default and includes ready-to-use guidance for:

- Codex via `AGENTS.md`
- Codex project config via `.codex/`
- Claude via `CLAUDE.md`
- Claude rules via `.claude/rules/`
- Cursor via `.cursor/rules/`

Use the same `AGENTS.md` and skills in any of these tools; only the entry file (e.g. `CLAUDE.md` or `.cursor/rules/`) is tool-specific. Local skills, checklists, templates, and memory scaffolding let the assistant improve over time instead of only reacting per session.

All repo references are relative, so anyone can clone this starter into any directory, rename the root folder, and customize it without rewriting absolute paths.

## What This Agent Is For

Use this starter when you want an AI collaborator that can:

- shape product requirements
- challenge weak assumptions
- review business workflows and edge cases
- turn screens or mockups into implementation-ready guidance
- review security, abuse cases, privacy, and operational risk
- read unfamiliar source code quickly and explain the real architecture
- hunt bugs, regressions, logic flaws, and misuse paths
- study a codebase first, understand its structure, and implement changes in the current style
- use a disciplined engineering workflow with planning, test-guided delivery, review, and verification
- apply coding best practices without fighting the existing project conventions
- harden the AI-agent workspace itself against unsafe permissions, prompt injection, and risky integrations
- pause safely when model limits are near, then continue cleanly on retry
- reason across programming languages and frameworks
- analyze competitors, trends, positioning, and market opportunities
- shape launch plans, roadmaps, and execution slices
- run project delivery with ownership clarity, milestones, and realistic execution cadence
- balance launch readiness across engineering, SEO/GEO, marketing, sales, monetization, and PMF learning
- sharpen ICP, buyer messaging, objections, proof points, and sales motion
- think about growth, distribution, SEO, GEO, and marketing strategy
- create copy-paste social content calendars with platform-by-platform dates and posting times
- plan Google Ads and paid acquisition with monetization logic tied to CAC and conversion
- make interfaces work cleanly across mobile, tablet, and desktop
- recommend the right AI model for a specific task, tradeoff, or workflow

## Expert Surfaces Included

- Product strategy and requirement shaping
- Planning, roadmap slicing, and execution strategy
- Project management and delivery coordination
- Launch commercialization and go-to-market execution
- UI/UX and design systems thinking
- Mobile-responsive design and implementation guidance
- Design-to-code translation
- Cybersecurity and risk review
- Business logic and workflow review
- Bug finding and code review
- Software architecture and system design
- Source-code analysis and repository understanding
- Code revamp and safe refactoring
- Coding best practices and implementation quality
- Engineering delivery workflow
- Context-limit continuation and retry-safe handoff
- Polyglot engineering across languages and frameworks
- Agent workspace security hardening
- Market research and competitive analysis
- Marketing, growth, and go-to-market thinking
- Social content planning and platform calendars
- Paid acquisition, Google Ads, and monetization strategy
- Sales strategy and buyer conversion thinking
- SEO and GEO strategy
- AI model recommendation and task-to-model fit
- Durable learning and promotion into stronger guidance

## Repo Structure

```text
bosskuAI/
├── AGENTS.md
├── CLAUDE.md
├── .codex/
├── .claude/rules/
├── .cursor/rules/
└── ai-assistant/
    ├── skills/
    ├── references/
    │   └── README.md   ← checklists & playbooks by division
    └── memory/
```

## How To Use

1. Put this starter at the root of the workspace you actually want the AI to work in.
2. Treat `AGENTS.md` as the source-of-truth instructions for the active workspace.
3. Use [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) for the actual setup flow after cloning.
4. Let project understanding draft `ai-assistant/memory/agent-profile.md` and `ai-assistant/memory/project-understanding.md` from repo evidence, then refine them.

## Terms

- **Skill** — A reusable workflow/lens (e.g. `bosskuai-engineering-delivery`). The assistant loads skills based on task type or when you explicitly ask.
- **Division** — Grouping in the roster (Product, Engineering, Design, Security, Marketing, Sales, etc.).
- **Explicit activation** — Saying e.g. "work as the security reviewer" so the right skill set and tone are applied.
- **Phase** — Stage of a larger effort (discovery → strategy → build → harden → launch); use phase-based prompts to run the right skills.
- **Memory** — Durable files under `ai-assistant/memory/` (agent-profile, project-understanding, etc.) shared across sessions and tools.

## Discover expertise (which expert when)

- **Skill roster**: In `AGENTS.md`, see **Skill roster (when to use which)** for a division-based table of every skill and when to use it.
- **What to ask for**: See **Quick reference: what to ask for** in `AGENTS.md` for situation → example phrasing and which skills get loaded.
- **Explicit activation**: You can say e.g. "work as the security reviewer" or "focus on launch commercialization" so the assistant loads that skill set and adopts that lens.
- **Phased pipelines**: For larger projects, see **Optional phased pipelines** in `AGENTS.md` (discovery → strategy → build → harden → launch) and which skills to lean on per phase.

## Start Here

- [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) — setup flow and "First time? Start here"
- [examples/sample-prompts.md](examples/sample-prompts.md) — quick reference, phase-based prompts (discovery / strategy / launch), and task prompts
- [examples/sample-agent-profile.md](examples/sample-agent-profile.md) — template for `ai-assistant/memory/agent-profile.md`
- [ai-assistant/references/README.md](ai-assistant/references/README.md) — references by division (checklists and playbooks per skill area)

## Customization

This starter is meant to be remixed.

- Rename the root folder if you want. The internal references are relative.
- Edit `AGENTS.md` to change posture, priorities, or expert surfaces.
- Fill in `ai-assistant/memory/agent-profile.md` with your domain, product, audience, and constraints, or ask project understanding to draft it dynamically first.
- Use `examples/sample-agent-profile.md` as a starting point if you want a filled example first.
- Add or remove skills under `ai-assistant/skills/`.
- Extend playbooks and checklists under `ai-assistant/references/`.
- Adjust `.codex/` if you want different Codex-specific roles or config defaults.

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
- Recommend the best-fit AI model by concrete model name before execution starts on meaningful tasks
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
