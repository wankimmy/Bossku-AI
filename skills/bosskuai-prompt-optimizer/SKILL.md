---
name: bosskuai-prompt-optimizer
description: "Use when the user asks to optimize, improve, or draft a prompt for BosskuAI; identify intent and gaps, select relevant skills, agents, and loops, and return a ready-to-paste prompt without executing it. Do not use for code or performance optimization."
license: MIT
metadata:
  author: affaan-m/ECC (community contribution by YannJY02)
  source: prompt-optimizer
---

# Prompt Optimizer

Analyze a draft prompt, critique it, match it to BosskuAI workspace components
(skills, agent contracts, loops, memory), and output a complete optimized
prompt the user can paste and run.

## When to Use

- User says "optimize this prompt", "improve my prompt", "rewrite this prompt"
- User says "help me write a better prompt for..."
- User says "what's the best way to ask BosskuAI to..."
- User pastes a draft prompt and asks for feedback or enhancement
- User says "I don't know how to prompt for this"
- User says "how should I use BosskuAI for..."

### Do Not Use When

- User wants the task done directly (just execute it)
- User says "optimize this code" or "optimize performance" — these are
  refactoring/profiling tasks (`bosskuai-code-revamp`, `bosskuai-performance-profiling`)
- User is asking about workspace setup (use `bosskuai-claude-code-setup`)
- User wants a skill inventory (use `bosskuai-skill-stocktake`)
- User says "just do it"

## How It Works

**Advisory only — do not execute the user's task.**

Do NOT write code, create files, run commands, or take any implementation
action. Your ONLY output is an analysis plus an optimized prompt.

If the user says "just do it" or "don't optimize, just execute", do not switch
into implementation mode inside this skill. Tell the user this skill only
produces optimized prompts, and instruct them to make a normal task request
if they want execution instead.

Run this 6-phase pipeline sequentially. Present results using the Output
Format below. Respond in the same language as the user's input.

### Phase 0: Project Detection

Before analyzing the prompt, detect the current project context:

1. Check `CLAUDE.md` / `AGENTS.md` in the working directory — read for project conventions
2. Detect tech stack from project files:
   - `composer.json` + `artisan` → Laravel / PHP
   - `nuxt.config.*` → Nuxt / Vue
   - `package.json` → Node.js / TypeScript / React / Next.js
   - `docker-compose.yml` → containerized stack (note services: db, redis, queue workers)
   - `pyproject.toml` / `requirements.txt` → Python
   - `go.mod` → Go; `Cargo.toml` → Rust; `build.gradle` / `pom.xml` → Java/Kotlin
3. Note detected tech stack for Phase 3 and Phase 4

If no project files are found (abstract prompt or new project), skip detection
and flag "tech stack unknown" in Phase 4.

### Phase 1: Intent Detection

Classify the user's task into one or more categories:

| Category | Signal Words | Example |
|----------|-------------|---------|
| New Feature | build, create, add, implement | "Build a login page" |
| Bug Fix | fix, broken, not working, error | "Fix the auth flow" |
| Refactor | refactor, clean up, restructure | "Refactor the API layer" |
| Research | how to, what is, explore, investigate | "How to add SSO" |
| Testing | test, coverage, verify | "Add tests for the cart" |
| Review | review, audit, check | "Review my PR" |
| Documentation | document, update docs | "Update the API docs" |
| Infrastructure | deploy, CI, docker, database | "Set up CI/CD pipeline" |
| Design | design, architecture, plan | "Design the data model" |
| Decision | should we, which option, go/no-go | "Monorepo or polyrepo?" |
| Business | pricing, GTM, leads, investors | "Plan the launch" |

### Phase 2: Scope Assessment

If Phase 0 detected a project, use codebase size as a signal. Otherwise,
estimate from the prompt description alone and mark the estimate as uncertain.

| Scope | Heuristic | Orchestration |
|-------|-----------|---------------|
| TRIVIAL | Single file, < 50 lines | Direct execution (indicator still shown) |
| LOW | Single component or module | Single skill + executor |
| MEDIUM | Multiple components, same domain | Plan → execute → audit, verification gate |
| HIGH | Cross-domain, 5+ files | Planner agent first (reasoning model), then phased execution |
| EPIC | Multi-session, multi-PR, architectural shift | `bosskuai-planning-execution` multi-session plan + `bosskuai-handoff` between sessions |

### Phase 3: BosskuAI Component Matching

Map intent + scope + tech stack (from Phase 0) to specific workspace components.
When unsure what exists, check `skill-index.json` (the live roster) instead of
guessing — `bosskuai-skill-stocktake` audits it.

#### By Intent Type

| Intent | Skills | Agent contracts |
|--------|--------|-----------------|
| New Feature | bosskuai-engineering-delivery, bosskuai-tdd-loop | planner, executor, auditor |
| Bug Fix | bosskuai-diagnose-loop | executor, auditor |
| Refactor | bosskuai-code-revamp, bosskuai-architecture-deepening | executor, auditor |
| Research | bosskuai-search-first, bosskuai-documentation-lookup, bosskuai-deep-research, bosskuai-grounding | — |
| Testing | bosskuai-tdd-loop, bosskuai-integration-testing, bosskuai-qa-automation-strategy | executor |
| Review | bosskuai-rigorous-code-review, bosskuai-greptile-review-loop, bosskuai-pr-check, bosskuai-grounding | auditor, final-reviewer |
| Documentation | bosskuai-claude-md-management (instruction files) | executor |
| Infrastructure | bosskuai-docker, bosskuai-devops-iac, bosskuai-vps-docker-deployment | — |
| Design (MEDIUM-HIGH) | bosskuai-software-architecture | planner |
| Design (EPIC) | bosskuai-planning-execution, bosskuai-grill-with-docs | planner, orchestrator |
| Decision | bosskuai-council | orchestrator |
| Business | cofounder + the matching growth/startup specialist | — |

#### By Tech Stack

| Tech Stack | Skills to Add |
|------------|--------------|
| Laravel / PHP | bosskuai-laravel-development, bosskuai-laravel-tdd, bosskuai-laravel-security, bosskuai-laravel-verification |
| Nuxt / Vue | bosskuai-nuxt-development |
| 3D / animation web | bosskuai-3d-web-development, bosskuai-gsap-animation, bosskuai-lenis-smooth-scroll |
| MongoDB | bosskuai-mongodb |
| Redis / queues | bosskuai-redis-caching-queues |
| SQL / schema design | bosskuai-database-engineering, bosskuai-data-architecture |
| Docker / VPS deploy | bosskuai-docker, bosskuai-vps-docker-deployment |
| Agent/LLM systems | bosskuai-agent-architecture-audit, bosskuai-eval-driven-agent-improvement |
| Other / unlisted | bosskuai-polyglot-engineering, bosskuai-coding-best-practices |

### Phase 4: Missing Context Detection

Scan the prompt for missing critical information. Check each item and mark
whether Phase 0 auto-detected it or the user must supply it:

- [ ] **Tech stack** — detected in Phase 0, or must user specify?
- [ ] **Target scope** — files, directories, or modules mentioned?
- [ ] **Acceptance criteria** — how to know the task is done?
- [ ] **Error handling** — edge cases and failure modes addressed?
- [ ] **Security requirements** — auth, input validation, secrets, tenant isolation?
- [ ] **Testing expectations** — unit, integration, E2E?
- [ ] **Performance constraints** — load, latency, resource limits?
- [ ] **UI/UX requirements** — design specs, responsive, a11y? (if frontend)
- [ ] **Database changes** — schema, migrations, indexes? (if data layer)
- [ ] **Existing patterns** — reference files or conventions to follow?
- [ ] **Scope boundaries** — what NOT to do?
- [ ] **Source of truth** — which files, docs, or data must the answer be grounded in, and may the model use general knowledge?

**If 3+ critical items are missing**, ask the user up to 3 clarification
questions before generating the optimized prompt (the `clarification`
indicator state). Then incorporate the answers.

### Phase 5: Workflow & Model Recommendation

Determine where this prompt sits in the lifecycle:

```
Research → Plan → Implement (TDD) → Review → Verify → Commit
```

This maps onto the BosskuAI pipeline: orchestrator/planner → executor →
auditor (+ `bosskuai-cybersecurity-risk` when risky) → final-reviewer.

**Model recommendation** (follow the workspace model split — reasoning model
to plan, coding model to execute; see `bosskuai-ai-model-selection`):

| Scope | Recommended split | Rationale |
|-------|------------------|-----------|
| TRIVIAL-LOW | Coding model only | Fast, cost-efficient; indicator still required |
| MEDIUM | Coding model + reasoning model for the plan step | Standard plan-first discipline |
| HIGH | Reasoning model (plan + audit) + coding model (execute) | Architecture and risk need the stronger model |
| EPIC | Reasoning model for the multi-session plan; coding model per phase | Deep decomposition once, cheap execution per slice |

Escalate execution to the high-risk route when the task touches auth,
payments, privacy, tenant isolation, migrations, production, or secrets.

**Multi-prompt splitting** (for HIGH/EPIC scope):

- Prompt 1: research + plan (`bosskuai-search-first`, then planner agent)
- Prompt 2-N: implement one phase per prompt, each ending with the
  verification gate (the stack's verification gate)
- Final prompt: integration test + `bosskuai-rigorous-code-review` across phases
- Between sessions: `bosskuai-handoff` writes the pickup doc;
  `bosskuai-context-limit-continuation` + `.bossku/memory/handoff.md`
  preserve state for the next tool/session

## Output Format

Present your analysis in this exact structure.

### Section 1: Prompt Diagnosis

**Strengths:** what the original prompt does well.

**Issues:**

| Issue | Impact | Suggested Fix |
|-------|--------|---------------|
| (problem) | (consequence) | (how to fix) |

**Needs Clarification:** numbered questions. If Phase 0 auto-detected the
answer, state it instead of asking.

### Section 2: Recommended BosskuAI Components

| Type | Component | Purpose |
|------|-----------|---------|
| Skill | bosskuai-tdd-loop | red→green→refactor discipline |
| Agent | auditor | post-implementation review |
| Model | reasoning model (plan) + coding model (execute) | scope-appropriate split |

### Section 3: Optimized Prompt — Full Version

The complete optimized prompt inside a single fenced code block,
self-contained and ready to copy-paste. Include:

- Clear task description with context
- Tech stack (detected or specified)
- Which skills/agents to load at each workflow stage
- Acceptance criteria
- Verification steps
- Scope boundaries (what NOT to do)
- Grounding: sources to quote from, and permission to say "not enough information"

### Section 4: Optimized Prompt — Quick Version

A compact version for experienced users:

| Intent | Quick Pattern |
|--------|--------------|
| New Feature | `Plan first (planner agent), implement via bosskuai-tdd-loop, review, run the verification gate.` |
| Bug Fix | `bosskuai-diagnose-loop: failing repro first, fix to green, regression test.` |
| Refactor | `bosskuai-code-revamp on [scope]; review; verify.` |
| Research | `bosskuai-search-first on [topic]; plan from findings.` |
| Review | `bosskuai-rigorous-code-review, then bosskuai-cybersecurity-risk if risky.` |
| Decision | `Convene bosskuai-council on: [question].` |
| EPIC | `bosskuai-planning-execution multi-session plan for "[objective]"; bosskuai-handoff between sessions.` |

### Section 5: Enhancement Rationale

| Enhancement | Reason |
|-------------|--------|
| (what was added) | (why it matters) |

### Footer

> Not what you need? Tell me what to adjust, or make a normal task request
> if you want execution instead of prompt optimization.

## Example

**User input:**

```
Add a REST API endpoint for user profile updates with validation
```

**Phase 0 detects:** `composer.json`, Laravel 12 backend

**Optimized Prompt (Full):**

```
Add a REST API endpoint for user profile updates (PATCH /api/users/{id}).

Tech stack: Laravel 12 (detected from project)

Requirements:
- PATCH /api/users/{id} — partial update of user profile
- Form Request validation for: name, email, avatar_url, bio
- Auth: Sanctum token required; users can only update their own profile (policy)
- 200 with updated user on success; 422 with validation errors; 401/403 for auth failures
- Follow existing controller/resource patterns in app/Http/
- Ground in: existing controllers under app/Http/ and the users migration; say so if a rule cannot be confirmed from them.

Workflow:
1. Plan the endpoint structure, policy, and validation rules (planner agent, reasoning model)
2. bosskuai-laravel-tdd — write feature tests first: success, validation failure,
   auth failure, updating another user's profile (must 403)
3. Implement following existing patterns
4. bosskuai-rigorous-code-review; load bosskuai-laravel-security for the policy/mass-assignment check
5. bosskuai-laravel-verification — pint, phpstan, full test suite, composer audit

Do not:
- Modify existing endpoints or the users migration
- Add new dependencies without bosskuai-search-first
```

## Related Components

| Component | When to Reference |
|-----------|------------------|
| `cofounder` | User hasn't routed the task yet |
| `bosskuai-skill-stocktake` | Audit which components exist (use instead of a hardcoded catalog) |
| `bosskuai-search-first` | Research phase in optimized prompts |
| `bosskuai-planning-execution` | EPIC-scope multi-session plans |
| `bosskuai-context-limit-continuation` | Long session context management |
| `bosskuai-ai-model-selection` / `bosskuai-cost-optimization` | Model and token-cost recommendations |
