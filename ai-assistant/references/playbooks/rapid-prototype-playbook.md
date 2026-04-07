# Rapid Prototype Playbook

## When to use
- Building an MVP or POC to validate a hypothesis before committing to full engineering
- Hackathon or time-boxed demo build (internal or investor)
- Testing a new integration or third-party tool before architecture commitment
- Quick proof-of-concept for a customer before signing a contract
- Exploring a new technical approach before deciding to adopt it

## Prerequisites
- Hard deadline confirmed (non-negotiable timebox)
- Success criteria defined before building starts
- Audience defined: internal / investor demo / user test (determines polish level)
- Stakeholder has reviewed the debt ledger and agreed to shortcuts

## Phase 1: Scope and Timebox

**Hard-scope vs. soft-scope:**

```markdown
## Prototype Scope: <Name> | <Date>

### Hard scope (must-have to call it done)
- [ ] <Feature 1>
- [ ] <Feature 2>

### Soft scope (nice-to-have if time allows)
- [ ] <Feature A>
- [ ] <Feature B>

### Out of scope (explicitly excluded)
- [ ] <Feature X — excluded because: deferred to v1>
```

**Timebox rules:**
- Split available time: 50% build, 20% debug/polish, 30% demo prep
- At 50% of time elapsed, check: is 50% of hard scope done? If not, cut soft scope immediately
- Never extend the timebox by adding scope — only extend if core functionality fails to work

**Success criteria template:**
> "Done when someone can **[specific demo action]** from start to finish without assistance."

Example:
> "Done when a non-technical user can sign up, connect their data source, and see their first chart — in under 5 minutes, without help."

## Phase 2: Tech Debt Ledger (fill before coding)

**This is mandatory. Write it before you write a single line of code.**

```markdown
## Tech Debt Ledger: <Prototype Name>

### Hardcoded values
- [ ] API keys in source code (must rotate before sharing externally)
- [ ] User IDs, tenant IDs hardcoded for demo
- [ ] Pricing/config values hardcoded

### Skipped error handling
- [ ] Empty state: no handling for empty data source
- [ ] Auth failure: not handled — will show a blank screen
- [ ] Network timeout: no retry logic

### Auth shortcuts
- [ ] No auth / open endpoint (acceptable: internal demo only)
- [ ] Mock auth with hardcoded credentials (acceptable: internal only)

### No tests
- [ ] Zero test coverage (acceptable for timebox: confirmed with <stakeholder>)

### Other shortcuts
- [ ] No pagination — will break with > 100 records
- [ ] No mobile layout
- [ ] In-memory state only — no persistence on refresh

### Review required before production use
- [ ] Security review of <X>
- [ ] Performance test with realistic data volume
- [ ] Replace hardcoded values with env vars / config system
```

**Debt ledger is reviewed with the stakeholder before coding starts — not after.**

## Phase 3: Build in Priority Order

**Critical path first:**
1. The thing the demo absolutely depends on
2. The data layer / integration that feeds it
3. The minimal UI to show it
4. Polish only if time allows

**Speed-over-quality heuristics (acceptable in a prototype):**
- Use a UI library or template — do not design from scratch
- Use the simplest database that works (SQLite, in-memory, JSON file)
- Use mock data for non-critical parts of the demo
- Hardcode the happy path — do not handle edge cases until validation
- Copy-paste over abstraction — one big function is fine if it ships
- No auth if demo is internal and credentials aren't real

**What is NOT acceptable even in a prototype:**
- Real user credentials stored insecurely
- Production database as the demo backend
- External calls to paid APIs without rate limiting (accidental cost)
- Secrets committed to git (use `.env` and `.gitignore` at minimum)

**Mid-build checkpoint (at 50% timebox):**
- [ ] Hard scope is ≥ 50% done
- [ ] If not: cut soft scope, simplify hard scope, alert stakeholder
- [ ] Demo path works end-to-end at least once (even if rough)

## Phase 4: Demo Readiness

**The demo must work on the demo machine, on demo data, before the demo.**

**Pre-demo checklist:**
- [ ] End-to-end happy path works from fresh state
- [ ] Demo script written (3–5 minute walk-through)
- [ ] Known failure points documented and a graceful bypass ready
- [ ] Backup: screenshots or screen recording in case live demo breaks
- [ ] Demo environment is isolated from development (no half-finished changes visible)

**Demo script template:**
```markdown
## Demo Script: <Prototype Name>

### Setup (before presenting)
- Open <URL>
- Log in as <demo user>
- Navigate to <starting screen>

### Walk-through (3–5 min)
1. "Here's the problem we're solving: ..." [30 sec]
2. "Here's how it works: ..." [2 min — show the happy path]
3. "Here's what this means: ..." [30 sec — so-what/outcome]

### Known gotchas
- If <X> breaks: navigate to <Y> instead or show screenshot
- If the data doesn't load: refresh once, then switch to backup recording

### Call to action
- What you want the audience to do or decide after the demo
```

## Phase 5: Handoff

**After the prototype, before any production work:**

```markdown
## Prototype Handoff: <Name> | <Date>

### What was built
<2-3 sentence description of what exists>

### What was proven
<The hypothesis this prototype validates or invalidates>

### What was NOT built (intentionally)
<Explicitly deferred items>

### Debt ledger status
<Link to debt ledger — all items still apply>

### Recommended next steps
1. <What to build properly if this is validated>
2. <What to throw away and re-architect>
3. <What shortcuts are acceptable to carry into v1 and which must be paid down first>

### NOT production-ready because
<Specific list of blockers before production use>
```

**Mark the prototype clearly in code:**
```javascript
// PROTOTYPE / DO NOT SHIP — <date>
// Shortcuts: <link to debt ledger>
// Validated: <hypothesis>
```

Or in a top-level `PROTOTYPE.md`:
```markdown
# PROTOTYPE — DO NOT SHIP

Built: <date>
Purpose: <what hypothesis this tested>
Status: <validated / invalidated / inconclusive>
Debt ledger: <link>
```

## Common Pitfalls

**Skipping the debt ledger** — shortcuts taken implicitly become invisible and end up in production. Writing them down is what keeps the prototype honest.

**Scope creep during the build** — "just one more thing" is how prototypes turn into production nightmares. Guard the hard scope ruthlessly.

**Polishing before the core works** — spending time on styling before the happy path works end-to-end is a common prototype killer. Always prove the critical path first.

**Demo-ing on dev environment** — live development machines have half-finished changes, console errors, and uncommitted files. Always demo on a clean state.

**Sharing externally without reviewing the debt ledger** — hardcoded credentials, open endpoints, and real data are safe for internal review but not external sharing. Review the ledger before any external demo.

**Not declaring the prototype obsolete** — if the prototype gets validated and real engineering starts, the prototype code must be explicitly deprecated or deleted. Prototype code that drifts into production is the most common source of production debt.
