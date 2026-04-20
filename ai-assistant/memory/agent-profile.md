# Agent Profile

Use this file to customize the starter for a specific company, product, or industry after cloning.

This file can be filled in two ways:

- manually by the user or team
- dynamically by project-understanding after reading the repo, docs, and nearby evidence

When the assistant drafts this file from project understanding:

- prefer confirmed facts from code, docs, copy, manifests, and source-of-truth files
- mark uncertain items as `Inferred:` or `Unknown`
- do not invent business details that are not supported by evidence
- ask the user only when the missing detail materially affects the next task

Suggested drafting rule:

- `Confirmed:` supported directly by repo evidence
- `Inferred:` likely true based on evidence, naming, or workflow
- `Unknown:` not yet supported by evidence

## Identity

- Company or project name: Confirmed: BosskuAI (repo name and README)
- Product name: Confirmed: BosskuAI — reusable AI workspace layer
- Industry: Confirmed: AI tooling / developer productivity
- Business model: Inferred: Open-source starter (MIT License); no monetization layer visible in the repo
- Stage: Confirmed: Active development (CHANGELOG shows ongoing skill additions); Inferred: pre-commercial / personal or small-team use

## Audience

- Primary user: Confirmed: Product-building teams who want one AI assistant that covers multiple expert surfaces (product, engineering, security, marketing, sales)
- Buyer: Inferred: Indie hackers, startup founders, small engineering teams, or solo developers running Claude / Cursor / Codex
- Secondary users: Inferred: Developers evaluating AI cofounder setups; teams wanting durable, cross-session AI behavior
- Geography or region: Unknown: No region-specific signals in the repo

## Product context

- Core problem: Confirmed: AI coding tools often lose continuity, duplicate rules across surfaces, and provide weak shared memory. BosskuAI packages a repeatable local workspace layer to reduce that drift.
- Main workflow: Confirmed: Clone → install into a project root → run workspace onboarding → customize `agent-profile.md` and `project-understanding.md` → use minimal skill routing per task → validate with local scripts and evals.
- Key differentiator: Confirmed: Tool-agnostic workspace contract; repo-local memory; install/sync scripts; lean always-loaded entry files; optional local-first retrieval and evaluation scaffolding.
- Main competitors: Inferred: Generic system prompt templates, Cursor rule bundles, personal instruction repos, and other local AI workspace overlays — no direct competitors named in the repo

## Operating preferences

- Preferred posture: Confirmed: Plan-first for meaningful tasks; smallest safe change; evidence-before-conclusion; challenge weak assumptions; triple-check important work
- Risk tolerance: Confirmed: Conservative on security, business logic, and AI workspace trust boundaries; treats security and abuse cases as first-class concerns
- Product priorities: Confirmed: Evidence-based conclusions; minimal churn; durable memory; skills promoted from repeated patterns; context-limit safety
- Delivery constraints: Confirmed: Repo must remain tool-agnostic and clone-portable (all internal references relative); no hardcoded paths
- Brand or tone notes: Confirmed: Pragmatic cofounder voice — concrete tradeoffs over generic advice; no flattery; clarity over vagueness

## Research notes

- Stable market truths worth preserving: Inferred: AI tools are narrowly trained as code generators by default; cofounder-style framing increases output quality for product and business decisions
- Security or compliance obligations: Confirmed: Agent workspace security is a first-class concern (agent-security-hardening skill, explicit rules against prompt injection, MCP misuse, external content distrust)
- UX quality bar: Confirmed: Responsive behavior, accessibility basics, and visual fidelity treated as part of quality for UI tasks applied to downstream projects
- Planning cadence: Inferred: No fixed release cadence observed; CHANGELOG tracks meaningful changes by capability area
- Marketing channels to prioritize: Unknown: No marketing or distribution signals in the repo
