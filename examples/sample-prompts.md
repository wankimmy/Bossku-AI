# Sample Prompts

Use these as starter prompts after cloning the repo.

## Workspace onboarding

```text
Use bosskuai-workspace-assistant first.

Classify the task, recommend the best AI model by concrete model name available in this tool with a short tradeoff note and fallback, and if repo context is still unclear use bosskuai-project-understanding before anything else.

Read the nearest README, manifests, docs, and enough source code to understand the project. Separate confirmed facts from inference.

Then draft:
- ai-assistant/memory/agent-profile.md
- ai-assistant/memory/project-understanding.md

Use Confirmed, Inferred, or Unknown where appropriate instead of guessing.
```

## Product strategy

```text
Act as a cofounder. Review this product idea, identify the real user pain, challenge weak assumptions, and turn it into a tighter product spec with acceptance criteria.
```

## Planning

```text
Create a realistic 30-60-90 day plan for this product. Separate must-do work from optional work, identify dependencies, and recommend the smallest meaningful next slice.
```

## Project management

```text
Use bosskuai-project-management.

Turn this initiative into a working delivery plan with owners, milestones, blockers, review cadence, decision checkpoints, and the smallest next action that reduces delivery risk.
```

## Launch commercialization

```text
Use bosskuai-launch-commercialization.

I want to launch this product. Balance the engineering readiness with business execution. Assess whether the product is ready enough to sell, whether the SEO and GEO are attractive enough for users to read and buy, and produce:
- a launch readiness assessment
- a comprehensive marketing plan
- a sales strategy for the country we want to sell in
- a monetization recommendation
- the main product-market-fit signals we need to validate next
```

## Engineering delivery

```text
Use bosskuai-engineering-delivery.

Plan this implementation first, then propose a test-guided delivery path, the smallest safe code changes, the review points, and the exact verification steps we should run before considering it done.
```

## UI/UX and design-to-code

```text
Review this screen and turn it into implementation-ready guidance. Cover hierarchy, spacing, states, interactions, mobile/tablet/desktop responsive behavior, and any missing UX details.
```

## Security and misuse

```text
Review this workflow for security risks, misuse paths, fraud opportunities, privacy exposure, and operational risk. Separate confirmed issues from inferred risks.
```

## Agent security hardening

```text
Use bosskuai-agent-security-hardening.

Audit this AI-agent workspace itself for prompt injection risk, unsafe external content, overbroad permissions, MCP risk, memory poisoning, secret exposure, and weak defaults. Recommend safer settings and guardrails for Claude, Cursor, and Codex.
```

## Business logic

```text
Trace this workflow and find business logic gaps, broken state transitions, approval flaws, retries, duplicates, and cancellation edge cases.
```

## Bug finding

```text
Review this code path like a bug hunter. Findings first. Focus on regressions, null paths, race conditions, contract drift, permission mistakes, and missing tests.
```

## Architecture

```text
Read this codebase and explain the current architecture from the source code, not just the docs. Then tell me where the main coupling and long-term risks are.
```

## Polyglot engineering

```text
Compare the best implementation approach for this feature in the actual stack we use. Explain what advice is stack-specific versus generally applicable.
```

## Market analysis

```text
Research this market and competitor set. Distinguish confirmed facts from inference, summarize the current landscape, and recommend a sharper positioning angle.
```

## Marketing and growth

```text
Design a go-to-market strategy for this product. Cover audience, messaging, channels, first experiments, funnel assumptions, and how to learn cheaply.
```

## Social content calendar

```text
Use bosskuai-social-content-calendar.

Create a 30-day content plan for LinkedIn, X, Instagram, TikTok, and Facebook for this product. Use my target country and timezone, and give me a copy-paste calendar with:
- date
- day
- local posting time
- platform
- post format
- topic
- hook
- CTA
```

## Paid acquisition and monetization

```text
Use bosskuai-paid-acquisition-monetization.

Create a practical paid growth plan for this product with Google Ads first. Include:
- country-specific channel recommendation
- campaign structure
- keyword or audience groups
- landing page needs
- budget test plan
- CAC and payback logic
- monetization and pricing recommendation
```

## Sales strategy

```text
Use bosskuai-sales-strategy.

Define the ICP, buyer, champion, likely objections, value and ROI framing, founder-led sales motion, and the next sales assets or outreach moves that can improve conversion.
```

## SEO and GEO

```text
Create an SEO and GEO strategy for this product. Map search intent, entities, content clusters, answer-first page structure, and high-leverage landing pages.
```

## AI model selection

```text
Recommend the best AI model for this task. Compare capability, speed, cost, context, coding ability, multimodality, and reliability tradeoffs, then give a fallback option.
```
