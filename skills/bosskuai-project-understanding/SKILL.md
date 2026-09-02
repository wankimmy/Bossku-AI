---
name: bosskuai-project-understanding
description: "Read a repo to learn what the project is, who it serves, what defines behavior, and which skills to load next — oriented reading plus selective sampling over full-tree dumps."
---

# BosskuAI Project Understanding

Use this skill when the first task is understanding the workspace before going deeper.

## Boundaries

- This skill answers: what is this project, for whom, with what stack, and where does truth live?
- For execution-path tracing or behavior-level debugging, switch to `bosskuai-codebase-analysis`.

## Workflow

1. Read orientation artifacts first: nearest README, `AGENTS.md`, `CLAUDE.md`, docs, manifests, env examples, CI/runtime config.
2. Read `.bossku/memory/handoff.md` and `.bossku/memory/project.md` first if they exist; leave the rest of memory until a task needs it.
3. Confirm documentation claims from real source code. Do not stop at README-level understanding.
4. Sample source intelligently:
   - entry points and framework config
   - one representative business/domain slice
   - data/model layer
   - integration boundaries
   - a few high-signal tests
5. Synthesize:
   - project purpose and likely users
   - stack and architecture style
   - code organization and source-of-truth files
   - confirmed facts vs inference vs unknowns
6. Update `.bossku/memory/project.md` (`bossku remember --kind project`) when durable understanding changed.
7. Recommend the next 1-3 most relevant skills.

## Guardrails

- Do not guess project purpose or constraints.
- Mark unsupported business details as `Inferred:` or `Unknown`.
- For large repos, prefer stratified sampling over “read everything”.

## Output

Return a concise summary covering:

- what the project is
- who it likely serves
- stack and architecture
- source-of-truth files
- confirmed vs inferred vs unknown
- recommended next skills
- memory files updated

## References

- `../../references/playbooks/project-understanding-playbook.md`
- `../../references/checklists/project-understanding-checklist.md`
- `.bossku/memory/project.md`
- `.bossku/memory/handoff.md`
