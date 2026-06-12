---
name: bosskuai-engineering-principles
description: "Apply the four principles before non-trivial coding — Think Before Coding, Simplicity First, Surgical Changes, Goal-Driven Execution. Pairs with coding-best-practices and rigorous-code-review."
---

# BosskuAI Engineering Principles

Four principles applied at the start of any non-trivial coding task. They reduce the most common LLM coding failures: wrong assumptions taken silently, overcomplicated code, drive-by edits, and ambiguous goals that need re-prompting.

Attribution: framing adapted from [Andrej Karpathy via forrestchang/andrej-karpathy-skills](https://github.com/forrestchang/andrej-karpathy-skills) (MIT). The four-principle structure is theirs; the BosskuAI specifics below are how this workspace operationalizes them.

## When to use this skill

Load this skill when:

- The task involves writing or modifying more than ~20 lines of code.
- The request is ambiguous, vague, or has multiple defensible interpretations.
- The change touches code you didn't write, or production-risky surfaces (auth, payments, migrations, jobs, webhooks).
- Routing has not yet picked a domain specialist, or the task spans multiple specialists.

Skip this skill for:

- Trivial fixes (typo, single-line config change, obvious one-liner).
- Tasks where a domain specialist is already loaded and its playbook covers the four principles in context.

## The four principles

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

- State the assumptions you're making in one line each. If any assumption is uncertain, ASK before coding — do not pick silently.
- If the request has more than one defensible interpretation, present them and let the user pick. Don't guess.
- If a simpler approach exists than the one the user asked for, name it. Push back when warranted.
- If something is unclear, stop. Name what's confusing, ask one focused question.

Failure mode this prevents: shipping the wrong thing efficiently because the agent guessed and didn't ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for code that has one caller.
- No "configurability" or "flexibility" the user didn't request.
- No error handling for impossible scenarios.
- If the diff is 200 lines and could be 50, rewrite it.

Test: would a senior engineer say this is overcomplicated? If yes, simplify before showing it.

Failure mode this prevents: a 600-line implementation of an 80-line feature, with abstractions that fight every future change.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd prefer a different one.
- If you notice unrelated dead code, mention it in the response — don't delete it.

When your changes create orphans:
- Remove imports/variables/functions YOUR changes made unused.
- Don't remove pre-existing dead code unless explicitly asked.

Test: every changed line should trace directly to the user's request.

Failure mode this prevents: a "fix one bug" PR that also reformats three files, renames a function, and breaks something unrelated.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform imperative tasks into verifiable goals:

| Vague task | Verifiable goal |
|---|---|
| Add validation | Write tests for invalid inputs, then make them pass |
| Fix the bug | Write a test that reproduces it, then make it pass |
| Refactor X | Tests pass before and after; no behavior change |
| Speed up the query | EXPLAIN ANALYZE shows X ms before, Y ms after; pick the threshold |
| Make it more secure | Name the threat model; write tests that prove the mitigation works |

For multi-step tasks, state the plan as verifiable steps:

```
1. [step] → verify: [exact check or command]
2. [step] → verify: [exact check or command]
3. [step] → verify: [exact check or command]
```

Strong success criteria let the agent loop independently. Weak criteria ("make it work") force constant re-prompting.

Failure mode this prevents: declaring a task done because the code compiles, then the user finds it doesn't actually solve their problem.

## Output contract

When this skill is active, every coding response should include:

```text
Assumptions: [bullets, one per line — explicit, ask if uncertain]
Plan: [3-5 verifiable steps, each with a `verify:` clause]
Diff scope: [files touched + one-line reason per file]
Verification: [exact commands, tests, or queries that prove the change works]
Out of scope: [what the request did NOT include but the agent noticed — flag, don't fix]
```

If any of these fields would be empty, that's usually the signal to ASK rather than ship.

## Specialist routing

After applying the four principles to frame the work, route to the relevant specialist for the actual implementation depth:

- Backend / Laravel → `bosskuai-laravel-development`
- Frontend / Nuxt → `bosskuai-nuxt-development`
- Database / SQL → `bosskuai-database-engineering`
- Cache / queues → `bosskuai-redis-caching-queues`
- Document DB → `bosskuai-mongodb`
- Infra / deploy → `bosskuai-vps-docker-deployment`
- Security review → `bosskuai-cybersecurity-risk`
- Code-quality detail → `bosskuai-coding-best-practices`
- Code review of a finished diff → `bosskuai-rigorous-code-review`

This skill provides the framing. The specialists provide the depth.

## Honesty rule

Do not invoke these principles as virtue-signaling. The signal that the principles are working is invisible: smaller diffs, fewer rewrites, clarifying questions before mistakes. If a response says "applying simplicity first" but ships 400 lines for an 80-line problem, the principle wasn't applied — it was named.

## References

- `../../references/playbooks/cofounder-decision-quality-playbook.md` — when-to-ASK and when-to-push-back rules align with principle 1.
- `../../skills/bosskuai-coding-best-practices/SKILL.md` — code-quality detail under principle 2.
- `../../skills/bosskuai-rigorous-code-review/SKILL.md` — review pass under principle 3.
- `../../skills/bosskuai-engineering-delivery/SKILL.md` — plan → execute → review delivery flow operationalizes principle 4 with author/reviewer separation.
