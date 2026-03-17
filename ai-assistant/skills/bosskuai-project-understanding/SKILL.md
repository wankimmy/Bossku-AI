---
name: bosskuai-project-understanding
description: Use this when reading a codebase or repository to understand what the project is about. Besides README and docs, read the whole source code and try to understand it; ask the user if not sure. Identifies stack, architecture, source-of-truth files, recommends expert skills, and stores durable understanding in memory.
---

# BosskuAI Project Understanding

Use this skill when the first task is understanding the current project or workspace before going deeper.

## Workflow

1. Read the nearest README, docs, manifests, and top-level structure.
2. Read the whole source code (or systematically through all key directories and files) and try to understand purpose, flows, and structure—not only a sampling. Use search and file reads to cover entry points, core modules, config, and domain logic.
3. Identify the stack, framework, entry points, architecture style, organizing conventions, and visible code quality norms from both docs and code.
4. Distinguish confirmed facts from inference.
5. Draft or update `../../memory/agent-profile.md` from the available evidence.
6. For agent-profile fields that are not directly supported, mark them as `Inferred:` or `Unknown` instead of guessing.
7. Recommend the most relevant expert skills for the next step.
8. Store durable project understanding in memory.
9. If you are not sure about material facts (purpose, ownership, constraints, or how something works), ask the user instead of guessing.

## Output expectation

- what the project is about
- likely users or customers
- core workflows or business purpose
- stack and architecture summary
- code organization and extension patterns
- code quality and style conventions
- source-of-truth files
- draft updates for `agent-profile.md`
- recommended next skills
- memory update recommendation

## References

- `../../references/playbooks/project-understanding-playbook.md`
- `../../references/checklists/project-understanding-checklist.md`
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
