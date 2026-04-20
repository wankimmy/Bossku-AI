---
name: bosskuai-project-understanding
description: Use this when reading a codebase or repository to understand what the project is about, who it serves, what defines behavior, and which skills should be loaded next. Prefer oriented reading plus selective source sampling over full-tree dumping.
---

# BosskuAI Project Understanding

Use this skill when the first task is understanding the workspace before going deeper.

## Boundaries

- This skill answers: what is this project, for whom, with what stack, and where does truth live?
- For execution-path tracing or behavior-level debugging, switch to `bosskuai-codebase-analysis`.

## Workflow

1. Read orientation artifacts first: nearest README, `AGENTS.md`, `CLAUDE.md`, docs, manifests, env examples, CI/runtime config.
2. Read `ai-assistant/memory/active-continuation.md` first. If `semantic-memory.sqlite3` exists, query it before opening broad memory files.
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
6. Update `../../memory/agent-profile.md` and `../../memory/project-understanding.md` when durable understanding changed.
7. If either indexed memory file changed, refresh recall with `python3 ./ai-assistant/scripts/vector_memory.py sync`.
8. Recommend the next 1-3 most relevant skills.

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
- `../../memory/agent-profile.md`
- `../../memory/project-understanding.md`
