---
name: bosskuai-project-understanding
description: Use this when reading a codebase or repository to understand what the project is about — purpose, users, stack, architecture, and source-of-truth files — using oriented reading plus a deliberate source sampling strategy for large trees. Recommends expert skills and stores durable understanding in memory; ask the user when facts cannot be confirmed.
---

# BosskuAI Project Understanding

Use this skill when the first task is understanding the current project or workspace before going deeper.

## How this differs from nearby skills

- **`bosskuai-codebase-analysis`**: traces *execution paths*, maps *modules and side effects*, and builds a predictive model of *how code runs*. **This skill** establishes *product and project context* (what it is, for whom, constraints, where behavior is defined) and a *navigation map* of the tree so deeper work loads the right files. Overlap is intentional at boundaries: stop here once you can summarize purpose and confidently point to entry points and core domains; switch to codebase-analysis when the task requires line-by-line tracing of a behavior.
- **`bosskuai-workspace-assistant`**: orchestrates across all skills; this skill is typically the first skill loaded when the codebase context is unknown.

## Mindset

- Read before concluding. The most expensive assumptions are the ones made without reading the code.
- Distinguish confirmed facts (from README, code, manifests) from inferences (likely true but not directly seen).
- If the project purpose, ownership, constraints, or a material behavior cannot be confirmed from files, **ask the user** instead of guessing.
- The output of this skill feeds all future agent sessions — write it to memory so it doesn't have to be rediscovered.

## Workflow

### Phase 1 — Read orientation artifacts

1. Read the nearest README, AGENTS.md, CLAUDE.md, docs/, and top-level directory structure.
2. Read package manifests: `package.json`, `pubspec.yaml`, `Cargo.toml`, `go.mod`, `pyproject.toml`, `requirements.txt`, `pom.xml` — whatever is present.
3. Read environment and config files: `.env.example`, `docker-compose.yml`, `Dockerfile`, CI/CD configs.
4. Identify the stated purpose, target users, and key features from documentation.

### Phase 2 — Read source code (sampling strategy)

5. Do not stop at documentation. Confirm behavior from real source, not only README claims.

6. **Small codebases** (rough heuristic: on the order of tens of files, or a single cohesive package): read entry points, core modules, domain logic, config, and test layout broadly enough that no major area is untouched.

7. **Large codebases** — use **stratified sampling** instead of “read everything”:
   - **Entry slice** — main / CLI / server bootstrap / app entry; framework config (e.g. routes, DI, Next config).
   - **Domain slice** — directories or packages named for the business (e.g. `orders`, `billing`, `auth`); one representative vertical read top-down.
   - **Data slice** — schema/migrations/models ORM layer; infer entities and lifecycles.
   - **Integration slice** — external API clients, webhooks, queues, email — wherever side effects leave the process.
   - **Test slice** — a few high-signal tests (e2e, integration) to see intended flows.
   - **Hotspot slice** — largest or most-churned modules per git history or size, if the task is general orientation.
   Expand slices only when uncertainty blocks the summary or the user’s next step; otherwise record **open uncertainties** and recommend **`bosskuai-codebase-analysis`** for deep tracing.

8. Identify: what is the actual core behavior? How does data flow through the system? What are the primary domain concepts?

### Phase 3 — Synthesize understanding

9. Identify the **stack**: language, runtime, framework, major dependencies, build system.
10. Identify the **architecture style**: monolith, layered, hexagonal, microservices, serverless, BFF, etc.
11. Identify **organizing conventions**: naming patterns, directory structure, test placement, error handling style.
12. Identify the **source-of-truth files**: which files define actual behavior (vs generated, compiled, or vendor files).
13. Note visible **code quality norms**: testing discipline, documentation quality, complexity level.

### Phase 4 — Write to memory

14. Draft or update `../../memory/agent-profile.md` with the project findings.
15. For `agent-profile.md` fields that cannot be confirmed, mark them as `Inferred:` or `Unknown` — never guess.
16. Draft or update `../../memory/project-understanding.md` with the durable summary.

### Phase 5 — Recommend next actions

17. Recommend the most relevant expert skills for the user's likely next step.
18. Flag open uncertainties — what still needs to be read or asked to complete the understanding.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not guess project purpose or constraints — read the source or ask the user if it cannot be confirmed from files.
- Do not stop at documentation — confirm behavior from real source code, not only README claims.
- Mark any unconfirmed field in `agent-profile.md` as `Inferred:` or `Unknown` — never fabricate context.

## Output format

```
Project summary:
  What it is: [one-sentence description]
  Likely users or customers: [who uses this]
  Core workflows: [primary user journeys or business functions]
  Business purpose: [what outcome this product/tool is trying to achieve]

Stack and architecture:
  Language / runtime: [stack]
  Framework: [framework]
  Architecture style: [monolith / layered / microservices / etc.]
  Major dependencies: [list]
  Build / test system: [tools]

Code organization:
  Top-level structure: [directory layout]
  Naming patterns: [conventions observed]
  Test placement: [co-located / separate / coverage level]
  Error handling style: [exceptions / typed errors / etc.]

Source-of-truth files:
  [file path] — [what it defines]

Code quality norms:
  Testing: [strong / mixed / weak / absent]
  Documentation: [strong / mixed / weak / absent]
  Complexity: [low / medium / high]

Confirmed vs inferred:
  Confirmed: [facts seen directly]
  Inferred: [reasonable assumptions — not verified]

Open uncertainties:
  [question — what would resolve it]

Memory updates:
  agent-profile.md: [updated / to update]
  project-understanding.md: [updated / to update]

Recommended next skills:
  [skill name] — [why relevant for next step]
```

## References

- `../../references/playbooks/project-understanding-playbook.md`
- `../../references/checklists/project-understanding-checklist.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
