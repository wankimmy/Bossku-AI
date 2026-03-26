---
name: bosskuai-search-first
description: Use this when deciding whether to adopt an existing package, service, MCP, internal utility, or pattern before building custom code or workflow logic.
---

# BosskuAI Search First

Use this skill when the task might already have a good existing solution and the main risk is reinventing the wheel too early.

## Workflow

1. Define the actual capability needed, the current stack, and any constraints before searching.
2. Check the current repo first for existing modules, utilities, scripts, commands, skills, or patterns that already cover the need.
3. Check whether the capability already exists in the current tool surface, local skills, MCPs, or platform defaults before adding new moving parts.
4. For technical implementation work, search for maintained libraries, framework-native features, or common patterns before proposing custom code.
5. Compare the main options using concrete tradeoffs: fit, maintenance, complexity, risk, portability, and operational cost.
6. Prefer adopt, extend, or wrap before building from scratch when that produces a cleaner long-term result.
7. If custom build is still the best path, explain why the alternatives were insufficient.

## Output expectation

- capability needed
- repo-local options already checked
- external or tool-level options considered
- adopt vs extend vs build recommendation
- main tradeoffs and risks
- smallest next step

## References

- `../../references/checklists/search-first-checklist.md`
- `../../references/playbooks/search-first-playbook.md`
- `../../references/checklists/codebase-analysis-checklist.md`
- `../../references/checklists/polyglot-engineering-checklist.md`
