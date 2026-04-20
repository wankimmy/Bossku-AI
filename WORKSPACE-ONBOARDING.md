# Workspace Onboarding

Use this once after installing BosskuAI into a real project.

## Goal

Set up a local-first workspace layer with:

- shared instructions across Claude Code, Cursor, and Codex
- initialized project memory
- synced local retrieval
- a simple validation pass

## Install

```bash
./scripts/install.sh /path/to/your/project
```

Windows:

```powershell
.\scripts\install.ps1 C:\path\to\your\project
```

Open the target project root after install.

## First Prompt

Paste this into the assistant:

```text
bossku

Use the workspace rules and the minimum relevant local skills.
Start with bosskuai-workspace-assistant. If repo context is unclear, use bosskuai-project-understanding.

Read the nearest README, manifests, docs, and enough source code to understand the project before making recommendations.

Separate:
- confirmed facts
- inferred facts
- unknowns

Then give me:
1. what this project is
2. the stack and architecture
3. the key source-of-truth files
4. which local skills will be most useful here
5. a draft for ai-assistant/memory/agent-profile.md
6. a draft for ai-assistant/memory/project-understanding.md
```

## Fill In Memory

Review and edit:

- [`ai-assistant/memory/agent-profile.md`](ai-assistant/memory/agent-profile.md)
- [`ai-assistant/memory/project-understanding.md`](ai-assistant/memory/project-understanding.md)

`ai-assistant/memory/active-continuation.md` is optional. Keep it empty, trim it, or delete it unless you are preparing a real handoff between sessions or tools.

Correct anything that is still inferred or unknown.

## Sync Retrieval

```bash
python3 ./ai-assistant/scripts/vector_memory.py sync
```

The default retrieval backend is `local-hash`. It is fast and local-first, but it is approximate. Treat it as a file finder, not as proof.

## Validate The Layer

```bash
bash ./scripts/check-workspace.sh
bash ./scripts/validate-skill-index.sh
python3 ./scripts/eval_workspace.py
```

## What Good Looks Like

- the assistant reads evidence before making repo-specific claims
- the assistant uses a small relevant skill set instead of loading everything
- durable memory updates are selective, not spammy
- retrieval returns sensible memory files for sample queries
- the eval report gives you a baseline for future changes
