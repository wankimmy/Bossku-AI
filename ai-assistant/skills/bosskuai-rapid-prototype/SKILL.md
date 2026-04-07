# Rapid Prototype

## When to use
- User needs a working proof-of-concept or demo in hours, not days
- Hackathon-style build where speed is the primary constraint
- Validating a hypothesis before committing to full engineering
- Scaffolding an MVP to show to investors, users, or stakeholders
- "Can we just get something working?" or "I need a demo by tomorrow"
- Exploring an unfamiliar stack or API before designing the real system

## How this differs from nearby skills
- **vs engineering-delivery:** engineering-delivery is plan-first with full quality gates, architecture review, and zero-debt default. rapid-prototype deliberately accepts tech debt with an explicit debt ledger — speed is the governing constraint.
- **vs software-architecture:** software-architecture designs systems for production longevity. rapid-prototype picks the fastest-to-run path regardless of long-term fit.
- **vs ui-ux-design-to-code:** ui-ux-design-to-code faithfully implements design systems and production UI patterns. rapid-prototype uses the fastest UI scaffold available (e.g., shadcn defaults, raw Tailwind) with zero pixel-perfection.
- **vs codebase-analysis:** rapid-prototype creates new things; codebase-analysis understands existing things.

## MCP requirements
- **Context7 (recommended):** Fetch live framework and library docs to pick the fastest path for unfamiliar tools. Graceful degradation: use training data if Context7 unavailable, but flag any version-sensitive assumptions.

## Workflow

### 1. Define the one thing the prototype must prove
Before writing a line of code, answer: "What is the single question this prototype answers?"
- Examples: "Can this API return results fast enough?", "Will users understand the 3-step flow?", "Does the ML model output look reasonable?"
- If there are multiple questions, rank them. The prototype only needs to answer #1.
- Write this as: **Prototype goal: [one sentence]**

### 2. Pick the fastest stack
Choose based on prototype type:

| Type | Recommended stack |
|---|---|
| Landing page / UI demo | Next.js + shadcn/ui or plain HTML + Tailwind CDN |
| API / backend proof | FastAPI (Python) or Hono (TypeScript) |
| Mobile demo | Expo (React Native) + NativeWind |
| Data pipeline / ML | Python notebook (Jupyter) or script + pandas |
| Full-stack demo | Next.js + Supabase (auth + DB in minutes) |
| Browser automation | Playwright script |
| CLI tool | Python or Node.js single-file script |

Criteria: fewest setup steps, most familiar to current codebase, least boilerplate.

### 3. Time-box explicitly
Agree on a time-box before starting:
- **2 hours:** Single screen, single API call, single concept
- **4 hours:** 2-3 screens or one end-to-end flow
- **8 hours:** Full demo with auth, basic data, and one core feature
- If the goal cannot be reached in the time-box, reduce scope — do not extend the time-box.

### 4. Build with explicit debt annotations
Mark every intentional shortcut in the code:
```python
# PROTO: hardcoded credentials — replace with env vars before production
# PROTO: no error handling — happy path only
# PROTO: mocked data — wire real API endpoint later
# PROTO: no auth — add Clerk/Supabase auth before production
```
These annotations feed directly into the debt ledger at the end.

### 5. Verify core hypothesis works
At the end of the time-box, explicitly check: does the prototype answer the goal stated in step 1?
- If yes: document what works and what was cut.
- If no: state why and what the next attempt would change. Do not silently ship a prototype that fails its own hypothesis.

### 6. Document the debt ledger
Complete the debt ledger before handing off the prototype:

```markdown
## Prototype Debt Ledger

### What was proven
[One-line answer to the prototype goal]

### What was skipped (must fix before production)
- [ ] Authentication / authorization
- [ ] Input validation and error handling
- [ ] Environment variable management (no hardcoded secrets)
- [ ] Database migrations and schema design
- [ ] Rate limiting / abuse protection
- [ ] Logging and observability
- [ ] Tests (unit, integration, E2E)
- [ ] [Any prototype-specific items]

### Upgrade path
Before any production use, run this prototype through: engineering-delivery skill
```

## Output format
- Working prototype code with `# PROTO:` annotations throughout
- Debt ledger (markdown, structured as above)
- One-paragraph summary: what was proven, what was cut, recommended next step

## Guardrails
- Never ship a prototype directly to production without first running it through engineering-delivery.
- The time-box is mandatory — do not negotiate it away after starting.
- If the prototype contains any real credentials, secrets, or PII — stop and flag immediately.
- The prototype goal (step 1) must be answered explicitly before declaring done.
- Do not use prototype patterns as templates for production code — the debt ledger defines what must change.
- If the user asks to "just deploy it quickly" — require a debt ledger review first.
