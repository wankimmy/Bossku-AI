# BosskuAI

A reusable **cofounder-style** workspace layer for Cursor, Claude, and Codex. Skills load by task across product, engineering, design, security, and GTM.

| Doc | Purpose |
|-----|---------|
| [AGENTS.md](AGENTS.md) | Skill roster, what to ask for, model split, memory rules |
| [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) | Onboarding prompt, per-tool notes, troubleshooting |

## Activation

Say `bossku` anywhere in a prompt to explicitly activate BosskuAI mode for that request.

- BosskuAI rules apply first.
- The assistant classifies the task and auto-loads the minimum relevant BosskuAI skills.
- You do not need to name a specific skill unless you want a particular expert lens.

Example asks:

- `bossku review this PR for security and business-logic risks`
- `bossku plan the smallest safe implementation for this feature`
- `bossku audit this repo and draft project-understanding`

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

- "bossku review this PRD and turn it into implementation slices."
- "bossku audit this flow for abuse, privacy, and logic flaws."
- "bossku run launch readiness across engineering, SEO, and GTM."

More: [examples/sample-prompts.md](examples/sample-prompts.md)

## License

MIT — see [LICENSE](LICENSE).
