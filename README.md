# BosskuAI

BosskuAI is a reusable workspace layer for Claude Code, Cursor, and Codex. It gives those tools a shared local setup for instructions, routing, memory, validation, and optional memory retrieval.

It is strongest when you want:

- one repo-local AI operating layer across multiple tools
- better continuity than chat history alone
- reusable routing through local skills
- a practical memory and handoff workflow for small teams or solo power users

It helps with consistency and recall. It does not guarantee lower token usage, perfect routing, or higher answer quality on every task.

## What the repo includes

- shared entry files: [`AGENTS.md`](AGENTS.md), [`CLAUDE.md`](CLAUDE.md), [`.codex/AGENTS.md`](.codex/AGENTS.md)
- shared memory under [`ai-assistant/memory/`](ai-assistant/memory)
- local skills under [`ai-assistant/skills/`](ai-assistant/skills)
- local-first retrieval in [`ai-assistant/scripts/vector_memory.py`](ai-assistant/scripts/vector_memory.py)
- validation and eval scripts under [`scripts/`](scripts)

## Install

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /path/to/your/project
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\path\to\your\project
```

For a full setup guide, use [`WORKSPACE-ONBOARDING.md`](WORKSPACE-ONBOARDING.md).

## How it works

1. A small always-loaded rule layer sets the cross-tool contract.
2. The assistant routes into one primary skill and, at most, one secondary skill.
3. Durable context is stored in files under `ai-assistant/memory/`.
4. Optional retrieval narrows memory lookup before broad file reads.
5. Local evals report prompt-surface size, routing-fit proxies, and retrieval hit quality.

## Local-First Retrieval

BosskuAI ships with a local SQLite-backed retrieval path.

```bash
python3 ./ai-assistant/scripts/vector_memory.py sync
python3 ./ai-assistant/scripts/vector_memory.py query "auth retry policy"
python3 ./ai-assistant/scripts/vector_memory.py status
```

Default mode uses a `local-hash` embedding approximation:

- no extra Python dependency by default
- local-first and fast to sync
- useful for narrowing which memory files to open next

It is not equivalent to a real embedding service. It can miss nuance, and it can still overmatch generic text. Important conclusions should be checked against the underlying memory file.

## Measurement

Run the included evals to inspect the workspace layer itself:

```bash
bash ./scripts/check-workspace.sh
bash ./scripts/validate-skill-index.sh
python3 ./scripts/eval_workspace.py
```

These checks are intended to support modest, defensible claims:

- prompt-surface size can be compared before vs after
- retrieval hit quality can be checked on sample queries
- routing-fit can be checked on sample task prompts

They do not prove end-task answer accuracy.

## Strongest Use Cases

- teams that switch between Claude Code, Cursor, and Codex
- projects that need durable handoff files, not just chat transcripts
- users who want local skills and shared workflows without a hosted control plane
- repositories that benefit from a lightweight memory and verification discipline

## Known Limitations

- The built-in retrieval backend is approximate and weaker than real embeddings.
- The eval suite measures workspace health, not true model intelligence.
- Skill quality still depends on the model loading the right specialist at the right time.
- Repo-local memory requires maintenance; stale memory can still reduce answer quality if left unchecked.
- This layer improves workflow consistency, but it cannot guarantee safer execution on its own.

## Key Files

- [`AGENTS.md`](AGENTS.md): canonical workspace contract
- [`skill-index.json`](skill-index.json): routing registry
- [`ai-assistant/references/workspace-layer-architecture.md`](ai-assistant/references/workspace-layer-architecture.md): architecture notes
- [`ai-assistant/references/memory-first-handoff-protocol.md`](ai-assistant/references/memory-first-handoff-protocol.md): shared memory protocol
- [`WORKSPACE-ONBOARDING.md`](WORKSPACE-ONBOARDING.md): first-run setup

## License

MIT — see [`LICENSE`](LICENSE).
