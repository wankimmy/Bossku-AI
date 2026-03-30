# BosskuAI

A reusable **cofounder-style** workspace layer for Cursor, Claude, and Codex. Skills load by task across product, engineering, design, security, and GTM.

| Doc | Purpose |
|-----|---------|
| [AGENTS.md](AGENTS.md) | Skill roster, what to ask for, model split, memory rules |
| [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) | Onboarding prompt, per-tool notes, troubleshooting |

## Setup

1. Clone this repo and install into your project root:

```bash
./scripts/install.sh /path/to/your/project
```

**Windows:**
```powershell
.\scripts\install.ps1 C:\path\to\your\project
```

- Use `--force` to overwrite existing files.
- Use `--skip-check` to skip workspace validation.

2. Open the **target project** in Cursor, Claude, or Codex (not this folder).

3. Paste the onboarding prompt from [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) to draft `ai-assistant/memory/agent-profile.md` and `ai-assistant/memory/project-understanding.md`.

## Repo layout

```text
bosskuAI/
├── AGENTS.md
├── CLAUDE.md
├── WORKSPACE-ONBOARDING.md
├── scripts/
│   ├── install.sh
│   ├── install.ps1
│   └── check-workspace.sh
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

Edit `AGENTS.md` for posture and priorities. Add or remove skills under `ai-assistant/skills/`. Write durable notes to `ai-assistant/memory/` after meaningful work.

## Example asks

- "Review this PRD and turn it into implementation slices."
- "Audit this flow for abuse, privacy, and logic flaws."
- "Run launch readiness across engineering, SEO, and GTM."

More: [examples/sample-prompts.md](examples/sample-prompts.md)

## License

MIT — see [LICENSE](LICENSE).
