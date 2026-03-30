# BosskuAI

A reusable **cofounder-style** workspace layer for Cursor, Claude, and Codex: product, engineering, design, security, GTM, and related skills load by task. Say things like *“work as the security reviewer”* to focus a specific lens.

| Doc | Use it for |
|-----|------------|
| [AGENTS.md](AGENTS.md) | Skill roster, “what to ask for,” model split, memory rules |
| [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) | Onboarding prompt (copy-paste), per-tool notes, troubleshooting |

Paths inside the repo are relative, so you can clone it anywhere and rename the folder.

## Quick setup

1. **Clone** this repo and `cd` into the `bosskuAI` folder.

2. **Install** into your real project root (the directory you open in your editor):

```bash
./scripts/install.sh /path/to/your/project
```

This copies `AGENTS.md`, `CLAUDE.md`, `WORKSPACE-ONBOARDING.md`, `.codex/`, `.claude/`, `.cursor/`, and `ai-assistant/`, then **runs the workspace check** so you know the layer is complete.

- Existing files in the target with the same names are **not** overwritten. To back them up and replace, use `--force`.
- To install without running the check: `--skip-check`.

**Windows (PowerShell):**

```powershell
.\scripts\install.ps1 C:\path\to\your\project
```

If `bash` is available (e.g. Git for Windows), validation runs automatically; otherwise, from the starter repo run:

```bash
./scripts/check-workspace.sh /path/to/your/project
```

3. **Open the target project** in Cursor, Claude, or Codex (not the starter folder).

4. **Onboard** — paste the *Workspace onboarding prompt* from [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) so the assistant drafts `ai-assistant/memory/agent-profile.md` and `ai-assistant/memory/project-understanding.md`, then fix anything marked `Inferred:` or `Unknown`.

### Optional: explore the starter first

Open the cloned `bosskuAI` folder as the workspace, read `AGENTS.md`, then either install into another project (step 2) or fill memory files manually using [examples/sample-agent-profile.md](examples/sample-agent-profile.md).

## What’s in the box

- **Behavior:** `AGENTS.md` at the project root; `CLAUDE.md` and mirrored rules for Claude; `.cursor/rules/` for Cursor; `.codex/` for Codex.
- **Skills & memory:** `ai-assistant/skills/`, `ai-assistant/memory/` (shared across tools).
- **References & helpers:** `ai-assistant/references/`, `ai-assistant/scripts/`, optional `ai-assistant/hooks/`.

## Repo layout

```text
bosskuAI/
├── AGENTS.md
├── CLAUDE.md
├── WORKSPACE-ONBOARDING.md
├── scripts/
│   ├── install.sh          # apply layer + verify (default)
│   ├── install.ps1
│   └── check-workspace.sh  # validate a workspace (also run by install)
├── .codex/
├── .claude/
├── .cursor/
└── ai-assistant/
    ├── skills/
    ├── memory/
    ├── references/
    ├── scripts/
    └── hooks/
```

## Customize

Edit `AGENTS.md` for posture and priorities, add or remove skills under `ai-assistant/skills/`, and extend `ai-assistant/references/`. After meaningful work, write durable notes back to `ai-assistant/memory/` so any tool can reuse them.

## Example asks

- “Review this PRD and turn it into implementation slices.”
- “Audit this flow for abuse, privacy, and logic flaws.”
- “Explain this codebase’s architecture from the source.”
- “Run launch readiness across engineering, SEO, and GTM.”

More prompts: [examples/sample-prompts.md](examples/sample-prompts.md).

## License

MIT — see [LICENSE](LICENSE).
