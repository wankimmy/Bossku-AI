# BosskuAI Agents

Subagent definitions for parallel, delegated work. Each agent is a specialized expert activated when the task matches its description.

## Format

Each agent file uses YAML frontmatter:

```yaml
---
name: <agent-name>
description: <one-line purpose + proactive activation conditions>
tools: ["Read", "Grep", "Glob"]          # read-only analysts
# or
tools: ["Read", "Write", "Edit", "Bash", "Grep", "Glob"]  # executors
model: opus    # planning, analysis, research
# or
model: sonnet  # execution, code generation, automation
---
```

## Cross-tool model mapping

| bosskuAI model | Claude Code | Codex | Cursor |
|----------------|-------------|-------|--------|
| opus | claude-opus-4-6 | gpt-5.4 | Strongest available |
| sonnet | claude-sonnet-4-6 | gpt-5.4-mini | Composer 2 |

## Agent roster

### Core (6)
| Agent | Model | Type | Purpose |
|-------|-------|------|---------|
| planner | opus | analyst | Implementation planning for complex features |
| code-reviewer | opus | analyst | Code quality, patterns, maintainability |
| security-reviewer | opus | analyst | Vulnerability detection, OWASP, auth/authz |
| build-fixer | sonnet | executor | Resolve build errors, type errors, deps |
| refactor-cleaner | sonnet | executor | Dead code, duplication, safe modernization |
| doc-updater | sonnet | executor | Keep docs in sync with code changes |

### Startup Research (6)
| Agent | Model | Type | Purpose |
|-------|-------|------|---------|
| market-researcher | opus | analyst | Multi-source market research with citations |
| competitor-tracker | opus | analyst | Automated competitor monitoring |
| financial-analyst | opus | analyst | Financial modeling, runway, unit economics |
| growth-experimenter | opus | analyst | A/B test and growth experiment design |
| lead-finder | opus | analyst | Lead discovery, scoring, outreach drafts |
| customer-researcher | opus | analyst | User interview analysis, JTBD, personas |

### Engineering (4)
| Agent | Model | Type | Purpose |
|-------|-------|------|---------|
| browser-agent | sonnet | executor | Browser automation via Playwright/Pencil |
| prototype-builder | sonnet | executor | Time-boxed prototyping with debt ledger |
| tdd-guide | sonnet | executor | RED/GREEN/REFACTOR TDD workflow |
| e2e-runner | sonnet | executor | End-to-end tests via Playwright |

### Cross-Tool (2)
| Agent | Model | Type | Purpose |
|-------|-------|------|---------|
| docs-lookup | sonnet | executor | Live docs via Context7 MCP |
| harness-optimizer | opus | analyst | bosskuAI health check and optimization |

## When to delegate

| Trigger | Agent(s) to use |
|---------|----------------|
| Task ≥5 independent files | Spawn parallel agents |
| ≥2 parallel workstreams | Delegate to matching agents |
| Market research needed | market-researcher |
| Competitor monitoring | competitor-tracker |
| Financial model / runway | financial-analyst |
| A/B test design | growth-experimenter |
| Lead list / outreach | lead-finder |
| User interviews / personas | customer-researcher |
| Browser automation / QA | browser-agent |
| Rapid POC / hackathon | prototype-builder |
| TDD workflow | tdd-guide |
| E2E test suite | e2e-runner |
| Framework API question | docs-lookup |
| Code review requested | code-reviewer + security-reviewer (parallel) |
| Build broken | build-fixer |
| harness drift / stale skills | harness-optimizer |

## Cursor compatibility

Cursor does not have native agent spawning. When a task matches an agent's description, Cursor's Composer should adopt that agent's **role**, **process**, and **output format** from the agent `.md` file, using the mapped model.

Open parallel Composer tabs — one per workstream — instead of running everything sequentially.
