---
name: bosskuai-project-understanding
description: Use this when reading a codebase or repository to understand what the project is about. Besides README and docs, read the whole source code and try to understand it; ask the user if not sure. Identifies stack, architecture, source-of-truth files, recommends expert skills, and stores durable understanding in memory.
---

# BosskuAI Project Understanding

Use this skill when the first task is understanding the current project or workspace before going deeper.

## How this differs from nearby skills

- **`bosskuai-codebase-analysis`**: deep-dives into execution paths, module structure, and code quality; this skill establishes the broader project context (purpose, stakeholders, domain) before that deeper analysis.
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

### Phase 2 — Read source code systematically

5. Do not stop at documentation. Read through the actual source code — entry points, core modules, domain logic, config, and test structure.
6. Cover all key directories, not just a sampling. If the project is large, read depth-first: pick the most important modules (by size, by name, by what the README emphasizes) and trace through them.
7. Identify: what is the actual core behavior? How does data flow through the system? What are the primary domain concepts?

### Phase 3 — Synthesize understanding

8. Identify the **stack**: language, runtime, framework, major dependencies, build system.
9. Identify the **architecture style**: monolith, layered, hexagonal, microservices, serverless, BFF, etc.
10. Identify **organizing conventions**: naming patterns, directory structure, test placement, error handling style.
11. Identify the **source-of-truth files**: which files define actual behavior (vs generated, compiled, or vendor files).
12. Note visible **code quality norms**: testing discipline, documentation quality, complexity level.

### Phase 4 — Write to memory

13. Draft or update `../../memory/agent-profile.md` with the project findings.
14. For `agent-profile.md` fields that cannot be confirmed, mark them as `Inferred:` or `Unknown` — never guess.
15. Draft or update `../../memory/project-understanding.md` with the durable summary.

### Phase 5 — Recommend next actions

16. Recommend the most relevant expert skills for the user's likely next step.
17. Flag open uncertainties — what still needs to be read or asked to complete the understanding.

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
