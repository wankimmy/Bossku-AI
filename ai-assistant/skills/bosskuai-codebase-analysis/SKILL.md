---
name: bosskuai-codebase-analysis
description: Use this for tracing execution paths, mapping call chains and module boundaries, identifying side effects, and summarizing how code actually behaves at runtime. Use bosskuai-project-understanding first when the project purpose and stack are still unknown.
---

# BosskuAI Codebase Analysis

Use this skill when the first task is understanding the codebase correctly before making any claims or changes.

## How this differs from nearby skills

- **`bosskuai-project-understanding`**: answers *what the project is for*, who uses it, stack, docs, and where truth lives — enough context to choose the next skills. **This skill** answers *how the code actually runs*: entry points, call chains, module boundaries, side effects, and extension points. Use project-understanding first when purpose is unclear; use codebase-analysis when you must trace behavior or map structure accurately.
- **`bosskuai-software-architecture`**: evaluates and redesigns architectural decisions; this skill reads and maps what currently exists without passing judgment.
- **`bosskuai-bug-finding`**: locates defects in a traced execution path; load this skill first to understand the path, then load bug-finding to locate defects within it.
- **`bosskuai-code-revamp`**: proposes structural changes; this skill is the prerequisite read before any revamp proposal.

## Mindset

- Read before you conclude. The codebase is the source of truth — not the README, not your assumptions, not the last conversation.
- Distinguish confirmed facts (seen in code) from inferences (likely true based on patterns).
- Generated code, compiled output, and support files are not the source of truth — find the originating source.
- The goal is a mental model of the system that is accurate enough to make correct predictions about behavior.

## Workflow

### Phase 1 — Orient

1. Identify the **entry points**: HTTP server, CLI entrypoint, main function, event listener, cron schedule.
2. Read the top-level structure: directory organization, major modules/packages, config files, test structure.
3. Identify the **stack**: language, runtime, framework, major dependencies, build system.
4. Distinguish source files from generated, compiled, or vendor files.

### Phase 2 — Trace execution paths

5. For the behavior in question, trace the **real execution path**:
   - Entry → Authentication/Authorization middleware → Routing → Handler → Business logic → Data access → Response/Side effects
6. At each step: what data flows in? what transforms happen? what can go wrong?
7. Identify **side effects** that are not immediately obvious: cache writes, event emissions, external calls, background jobs triggered.

### Phase 3 — Map the architecture

8. Identify the **architectural style**: monolith, layered, hexagonal, microservices, event-driven, serverless, BFF, etc.
9. Map the **module/package boundaries**: what does each major module own? where are the boundaries?
10. Identify **data ownership**: which module is the source of truth for each domain entity?
11. Identify **integration points**: external APIs, message queues, databases, cache, third-party SDKs.

### Phase 4 — Identify quality signals

12. Scan for **tech debt markers**:
    - TODOs/FIXMEs in critical paths
    - Commented-out code blocks
    - Duplicate business logic in multiple modules
    - Deep nesting (4+ levels)
    - Functions over 100 lines
    - Missing error handling in critical paths
    - Missing or absent tests in core modules

13. Scan for **dead code signals**:
    - Exported functions/classes with no internal callers
    - Feature flags that are always true or always false
    - Unreachable branches
    - Deprecated modules still imported

### Phase 5 — Identify extension points

14. Where does the codebase expect changes to be made? (plugin pattern, strategy pattern, config-driven behavior)
15. What are the **safest places to add new behavior** without touching existing logic?
16. What are the **highest-risk areas** where changes are likely to cause regressions?

### Phase 6 — Summarize

17. State clearly what is **confirmed** (directly seen in code) vs **inferred** (pattern-based).
18. List open uncertainties and what would need to be read or tested to resolve them.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not make claims about code behavior without reading the actual source — README and docs are aspirational.
- Do not treat generated, compiled, or vendor files as the source of truth.
- Distinguish confirmed facts (directly seen in code) from inferences (pattern-based) in every output.

## Output format

```
Stack and entry points:
  Language / runtime: [stack]
  Frameworks: [list]
  Entry points: [list with file paths]
  Build / test system: [tools]

Architecture:
  Style: [monolith / layered / hexagonal / microservices / etc.]
  Major modules: [module — what it owns — key files]
  Data ownership: [entity — owning module]
  Integration points: [external systems — how connected]

Execution path (for [behavior]):
  [entry] → [middleware] → [handler] → [logic] → [data access] → [response / side effects]
  Side effects not immediately obvious: [list]

Code quality signals:
  Tech debt markers: [location — issue]
  Dead code candidates: [location — signal]
  Strong patterns: [what the codebase does well]
  Weak patterns: [what needs attention]

Extension points:
  Safest places to add behavior: [list]
  High-risk areas (change carefully): [list]

Confirmed vs inferred:
  Confirmed: [facts seen directly in code]
  Inferred: [reasonable assumptions from patterns]

Open uncertainties:
  [question — what would resolve it]
```

## References

- `../../references/playbooks/codebase-analysis-playbook.md`
- `../../references/checklists/codebase-analysis-checklist.md`
- `../../references/pitfalls/performance-pitfalls.md` (hot paths, N+1, unbounded reads while tracing)
