# Token-saving workflow

BosskuAI reduces wasted tokens via **narrow context** and **staged agents**. Use with [`AGENTS.md`](../AGENTS.md) and agent files in [`agents/`](../agents/).

## Before execution

- Read only relevant files (planner `target_file_list` when present).
- Do **not** scan the whole repo unless needed.
- Ask orchestrator for a **compact** task plan.
- Retrieve memory **only when useful**; summarize old blobs before injecting.
- Prefer semantic query: `python3 ai-assistant/scripts/auto_memory.py query "<task>" --limit 5`

## During execution

- Modify only required files / sections.
- Avoid repeating full files in replies — use summaries or selective diff snippets.
- Keep executor focused on implementation; defer architecture essays to orchestrator phase.

## During audit

- Auditor inspects **changed files first** (`agents/auditor.md`).
- Expand scope only when risk signals appear.

## Final response

Include (concise):

- What changed  
- Files changed  
- Tests/checks run  
- Risks or notes  
- Next recommended step  
