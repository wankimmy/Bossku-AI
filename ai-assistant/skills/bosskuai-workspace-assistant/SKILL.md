---
name: bosskuai-workspace-assistant
description: Use this for general work in a workspace using this starter, or when a task spans multiple expert surfaces such as product, planning, UI/UX, bug-finding, rigorous code review, software architecture, source-code analysis, security, business logic, marketing, and market analysis.
---

# BosskuAI Workspace Assistant

Use this skill to orchestrate work across the current workspace when no single expert skill covers the full task, or when you need to route a task to the right skill first.

## How this differs from other skills

This is the **orchestration layer** — it does not replace expert skills, it routes to them. If the task is clearly within one expert domain, load that skill directly. Load this skill when:
- The task spans multiple domains (e.g., launch readiness = engineering + marketing + SEO)
- The domain is unclear and routing is needed
- The workspace context (project purpose, stack) is not yet established

## Routing logic

### Step 1: Classify the task

| Task type | Load first |
|-----------|-----------|
| Unfamiliar codebase or repo | `bosskuai-project-understanding` |
| Code-level defect, failure, regression | `bosskuai-bug-finding` |
| Diff review for quality and correctness | `bosskuai-rigorous-code-review` |
| Implementation task (new feature, refactor) | `bosskuai-engineering-delivery` |
| Architecture design or review | `bosskuai-software-architecture` |
| Structural cleanup or legacy migration | `bosskuai-code-revamp` |
| Code quality at implementation level | `bosskuai-coding-best-practices` |
| Multi-language or framework-specific guidance | `bosskuai-polyglot-engineering` |
| Map execution paths and module structure | `bosskuai-codebase-analysis` |
| Should we adopt a library or build custom? | `bosskuai-search-first` |
| Capture or promote durable learnings | `bosskuai-continuous-learning` |
| UI/UX review or design-to-code | `bosskuai-ui-ux-design-to-code` |
| Application security, threat modeling | `bosskuai-cybersecurity-risk` |
| AI agent workspace security | `bosskuai-agent-security-hardening` |
| Business rule and logic correctness | `bosskuai-business-logic-review` |
| Product strategy and scoping | `bosskuai-product-strategy` |
| Incident debugging with DB state or logs | `bosskuai-bug-finding` (deep investigation mode) |
| Analytics, funnels, experiments, instrumentation | `bosskuai-analytics-metrics` |
| Planning and prioritization | `bosskuai-planning-execution` |
| Milestone and delivery tracking | `bosskuai-planning-execution` |
| Full launch plan | `bosskuai-launch-commercialization` |
| Market research and competitive analysis | `bosskuai-market-analysis` |
| API contract design | `bosskuai-api-design` |
| Data modeling, migrations, or analytics pipelines | `bosskuai-data-architecture` |
| Marketing strategy and growth loops | `bosskuai-marketing-growth` |
| CI/CD, containers, infra, runtime operations | `bosskuai-devops-iac` |
| Performance profiling, bottleneck diagnosis, flame graphs | `bosskuai-performance-profiling` |
| Integration test design, contract tests, test fixtures | `bosskuai-integration-testing` |
| Active incident triage, postmortem, severity escalation | `bosskuai-incident-response` |
| Paid channels and monetization | `bosskuai-paid-acquisition-monetization` |
| Localization, locale handling, multilingual UX | `bosskuai-i18n-l10n` |
| SEO and GEO discoverability | `bosskuai-seo-geo` |
| Privacy and compliance readiness | `bosskuai-legal-compliance` |
| Social content calendar | `bosskuai-marketing-growth` |
| Sales motion, ICP, deal qualification | `bosskuai-sales-strategy` |
| Which AI model for this task | `bosskuai-ai-model-selection` |
| Current model is stuck, low-confidence, or missing capability | `bosskuai-cross-model-escalation` |
| Context or token limit approaching | `bosskuai-context-limit-continuation` |
| Heavy, parallel, or risky task | `bosskuai-subagent-delegation` |
| Skill quality audit | `bosskuai-skill-stocktake` |
| Extract rules from patterns | `bosskuai-rules-distill` |

### Step 2: Select the model

Before any meaningful task, state the model:
- Planning / strategy / analysis → `claude-opus-4-6`
- Implementation / code generation / execution → `claude-sonnet-4-6`
- Quick lookups / single-line fixes → skip the split

### Step 3: Load only what is needed

Load the 1–2 skills most relevant to the current task. Do not load all skills speculatively — each loaded skill consumes context. Load additional skills only when their specific domain is needed.

## General operating rules

1. **Read before concluding**: Study code, docs, and specs before making claims or proposals.
2. **Minimal safe changes**: Prefer the smallest change that fits the current architecture.
3. **Plan before executing**: For meaningful tasks, state the plan and get alignment before implementation.
4. **Distinguish facts from inference**: Mark inferences explicitly. Ask when unsure.
5. **Triple-check before finalizing**: Especially for architecture decisions, security findings, and irreversible actions.
6. **Preserve handoff state**: If context limits are approaching, write a compact handoff state before truncation.
7. **Promote durable learnings**: After meaningful tasks, decide whether a finding belongs in memory, a checklist, playbook, or skill.

## Definition of Done gate

**Never declare a task done without running this checklist.** "Done" means all of the following pass — not just "code written" or "response provided":

| Item | What it means |
|------|--------------|
| **Files applied** | Every change is written to disk, not just shown in a code block |
| **Requirement re-read** | Original request re-read verbatim; confirmed satisfied |
| **Edge cases** | Empty, null, boundary, and error states considered |
| **No regressions** | Adjacent behavior unchanged or intentionally changed |
| **Build/tests pass** | Compiled, tests run and passed — not assumed |
| **Triple-check** | Implementation re-read from the actual saved file, not from memory |
| **Self-diff review** | Change does exactly what it claims, no more, no less |
| **Security minimum** | No unvalidated trust boundaries, no hardcoded/logged secrets |
| **Gaps named** | Anything NOT verified is stated explicitly |

If any item fails: continue working. If an item cannot be verified in this context, name it in the residual risks.

## Context limit check

**Concrete trigger thresholds — switch to `bosskuai-context-limit-continuation` when any is met:**

| Signal | Threshold |
|--------|-----------|
| Files read this session | > 8 files |
| Total lines read this session | > 1,500 lines |
| Conversation turns | > 15 back-and-forth turns |
| Remaining task phases | ≥ 3 phases still to go |
| Single file size | > 400 lines → read targeted range only |

**Pre-task budget estimation (mandatory before any multi-file task):**
Before starting a task touching ≥ 3 files or with ≥ 3 phases:
1. Estimate: (files × ~200 lines) + (skills × ~150 lines) + output (~200–500 lines)
2. If total > 1,200 lines: warn the user, propose a phased plan, confirm scope before starting
3. If total is 1,000–1,500 lines (tight): state this upfront and plan a clean stopping point

Additional practices:
- Load only the relevant skills (not all of them)
- Prefer targeted file reads (specific line ranges) over full reads
- Grep before full reads on large files

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not load all skills speculatively — load only the 1–2 skills most relevant to the current task; each loaded skill consumes context.
- Do not skip model selection — state planning model vs execution model at the start of every meaningful task.
- Do not declare a task done without running the Definition of Done checklist.

## Output expectation

- State the expert skill(s) loaded and the model selected.
- Apply the workflow from the loaded skill(s).
- Surface plan → get alignment → execute.
- After the task: identify any durable learning worth promoting.

## References

- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/checklists/continuous-learning-checklist.md`
- `../../references/session-handoff-template.md`
- `../../references/playbooks/continuous-learning-playbook.md`
- `../../memory/learning-log.md`
- `../../memory/bug-patterns.md`
- `../../memory/market-notes.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
