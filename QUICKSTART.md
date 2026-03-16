# Quickstart

Use this file to get the most value from the repo quickly after cloning.

## 1. Clone or copy

Clone the repo into any folder name you want. The internal references are relative, so the setup is portable.

## 2. Customize the agent

Open and fill:

- `.codex-assistant/memory/agent-profile.md`

At minimum, define:

- product name
- industry
- primary user
- buyer
- core problem
- key differentiator
- main competitors
- risk tolerance
- product priorities

## 3. Treat `AGENTS.md` as source of truth

The assistant should use:

- `AGENTS.md` for core behavior
- `.cursor/rules/` for Cursor
- `.claude/rules/` and `CLAUDE.md` for Claude
- `.codex-assistant/skills/` for specialized workflows

## 4. Start with one of these workflows

### Product review

Prompt example:

```text
Review this PRD like a cofounder. Challenge weak assumptions, tighten acceptance criteria, identify risks, and recommend the smallest delivery slice.
```

### Codebase analysis

Prompt example:

```text
Read this codebase and explain the real architecture, major entry points, and likely technical risks. Only claim what is supported by the source code.
```

### Bug and logic review

Prompt example:

```text
Review this feature path for bugs, business logic flaws, misuse paths, and missing tests. Findings first.
```

### GTM and marketing

Prompt example:

```text
Act as a cofounder and propose a go-to-market plan, messaging strategy, and first 5 growth experiments for this product.
```

### SEO and GEO

Prompt example:

```text
Create an SEO and GEO strategy for this product, including search intent, content clusters, entity structure, and high-leverage landing pages.
```

### AI model selection

Prompt example:

```text
Recommend which AI model is best for this task. Compare capability, speed, cost, tool use, multimodality, and reliability tradeoffs.
```

## 5. Expect this operating style

This agent should:

- read the nearest evidence first
- challenge weak assumptions
- triple-check important conclusions
- ask when something material is not confirmed
- distinguish explicit facts from inference

## 6. Keep it improving

Use these files after meaningful work:

- `.codex-assistant/memory/learning-log.md`
- `.codex-assistant/memory/bug-patterns.md`
- `.codex-assistant/references/checklists/learning-promotion-checklist.md`
- `.codex-assistant/references/session-handoff-template.md`

## 7. Suggested first edits after cloning

- review the default MIT license and change it if you need a different one
- update `agent-profile.md`
- remove skills you know you do not need
- add domain-specific skills for your industry if necessary
- add example prompts or output formats specific to your team
