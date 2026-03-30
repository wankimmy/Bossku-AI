---
name: bosskuai-search-first
description: Use this when deciding whether to adopt an existing package, service, MCP, internal utility, or pattern before building custom code or workflow logic.
---

# BosskuAI Search First

Use this skill when the task might already have a good existing solution and the main risk is reinventing the wheel too early.

## How this differs from nearby skills

- **`bosskuai-codebase-analysis`**: reads and maps what exists in the current codebase; this skill also checks external libraries, tools, and platform features before proposing to build.
- **`bosskuai-polyglot-engineering`**: advises on language/framework-specific implementation; this skill determines whether to build at all.

## Mindset

- The best code is code you don't write. Every custom implementation is a maintenance cost.
- Existing solutions beat custom builds when: they are well-maintained, they fit the stack, and the integration cost is lower than the build cost.
- Custom builds beat existing solutions when: no solution fits, the integration complexity exceeds the build complexity, or the use case is a core differentiator.
- Recency matters: a library that was the right choice in 2020 may have better alternatives now.

## Search order (apply in this order — stop when you find a good fit)

1. **In the current repo**: Is there already a utility, module, script, skill, or pattern that covers this need?
2. **In the current framework / platform**: Does the framework have a built-in feature that handles this? (e.g., framework-native auth, built-in caching, platform SDK)
3. **In the current tool surface**: Does the agent tool surface, MCP server, or platform offer this capability natively?
4. **In the ecosystem (published libraries)**: Is there a well-maintained, widely-used library for this?
5. **As a managed service**: Is there a hosted service that removes the operational cost entirely?
6. **Build custom**: Only if none of the above are sufficient.

## Evaluation criteria for external solutions

Before recommending adopt or extend, evaluate:

| Signal | Green | Amber | Red |
|--------|-------|-------|-----|
| **Maintenance** | Active commits, issues triaged | Last commit < 1 year, slow issues | Last commit > 1 year, abandoned |
| **Adoption** | 1k+ GitHub stars, high weekly downloads | Moderate adoption | Niche, <100 stars |
| **Security** | No open CVEs, responsive to advisories | 1–2 low CVEs | Open high/critical CVEs |
| **License** | MIT, Apache 2.0, BSD | LGPL (check linking rules) | GPL, proprietary, unclear |
| **Fit** | Covers 90%+ of the use case | Covers 70%, needs extension | Requires significant wrapping |
| **Portability** | Works with current stack version | Needs version upgrade | Incompatible |

A single Red signal is a blocker. Multiple Amber signals should trigger a build-vs-adopt discussion.

## Build vs adopt decision

| Scenario | Recommendation |
|----------|---------------|
| Existing solution scores all Green | **Adopt** — use it as-is |
| Existing solution scores Green/Amber with minor gaps | **Extend** — wrap or augment it |
| Existing solution scores multiple Amber | **Adopt with caution** — document the risks, plan for replacement |
| No solution fits OR core differentiator | **Build custom** — explain why alternatives were insufficient |
| Build cost exceeds ongoing managed service cost | **Managed service** — factor in ops cost |

## Workflow

1. **Define the actual capability needed**: Be specific about what the code or tool must do — not the implementation, the behavior.
2. **State constraints**: stack version, license requirements, latency requirements, operational constraints.
3. **Search in order**: current repo → framework native → tool surface → ecosystem → managed service.
4. **Evaluate candidates**: score each against the evaluation criteria above.
5. **Recommend adopt / extend / build**: with clear reasoning.
6. **Identify the smallest next step**: if adopting, what is the integration path? If building, what is the minimal first version?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not recommend building custom code when a maintained library covers the use case well.
- Do not adopt a library without checking its security posture and license.
- Do not reject an external solution just because it requires reading docs — reading docs beats maintaining custom code.
- Do not recommend "we can just use X" without verifying X is still maintained and fits the current stack.

## Output format

```
Capability needed:
  [specific behavior required, not implementation]
  Constraints: [stack / license / latency / ops]

Search results:
  In-repo: [found / not found — details]
  Framework native: [found / not found — details]
  Tool surface: [found / not found — details]
  Ecosystem: [candidates with scores: maintenance / adoption / security / license / fit]
  Managed service: [options considered]

Recommendation: [Adopt / Extend / Build custom]
  [chosen option] — [why it fits] — [why alternatives were insufficient]

Integration path / build scope:
  [smallest next step to use or build the solution]

Tradeoffs:
  [adopt option vs build custom] — [fit / maintenance / complexity / risk / portability / cost]
```

## References

- `../../references/checklists/search-first-checklist.md`
- `../../references/playbooks/search-first-playbook.md`
- `../../references/checklists/codebase-analysis-checklist.md`
- `../../references/checklists/polyglot-engineering-checklist.md`
