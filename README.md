# BosskuAI

## What is this?

BosskuAI is a reusable AI workspace layer for **Claude Code, Cursor, and Codex**.

It gives those tools:

- shared rules
- shared memory
- task routing through local skills
- plan-first behavior for meaningful work
- optional vector-backed long-term memory retrieval

You install it into a real project so the assistant behaves more like a practical teammate than a generic chatbot.

## Who is this for?

Use BosskuAI if you:

- work across Claude Code, Cursor, or Codex
- want one shared assistant setup across projects
- want better continuity across chats and sessions
- want built-in workflows for engineering, product, design, security, growth, and research
- are tired of re-explaining project context every time

## Why use this?

BosskuAI helps when normal AI usage starts to feel stateless or inconsistent.

Main benefits:

- **Shared memory**: plans, learnings, and project context live in files, not only chat history
- **Better routing**: the assistant loads the right local skill instead of giving generic answers
- **Less drift**: rules stay consistent across Claude, Cursor, and Codex
- **Longer continuity**: vector retrieval helps recall relevant durable memory across sessions
- **Safer execution**: meaningful tasks are expected to plan first, then execute, then log durable outcomes

## How do I use it?

### 1. Install it into your project

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /path/to/your/project
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\path\to\your\project
```

### Install it as a Claude Code plugin

If you want to distribute BosskuAI through GitHub as a Claude Code plugin marketplace:

```bash
/plugin marketplace add wankimmy/Bossku-AI
/plugin install bossku-ai@bosskuai-marketplace
```

After install, a simple entrypoint is:

```bash
/bossku-ai:cofounder
```

Example prompts:

```text
/bossku-ai:cofounder We have 3 weeks of runway extension left. What should we prioritize?
/bossku-ai:cofounder Help me decide whether to ship this feature, cut scope, or talk to customers first.
```

For local testing before pushing:

```bash
claude --plugin-dir .
```

### 2. Open your real project

Open the target project root in Claude Code, Cursor, or Codex.

### 3. Run onboarding once

Use [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) to initialize project memory and confirm the setup.

### 4. Prompt normally

Say `bossku` in the prompt when you want BosskuAI behavior explicitly activated.

Examples:

```text
bossku review this PR for security and business-logic risks
bossku plan the safest implementation for this feature
bossku investigate why this flow keeps regressing
```

### 5. Let memory improve over time

BosskuAI stores durable context in `ai-assistant/memory/`.

For non-trivial work, the intended flow is:

1. read memory
2. plan
3. store compact reusable plan when it matters
4. sync vector memory
5. execute
6. store durable outcomes or learnings
7. sync again if indexed memory changed

Vector memory commands:

```bash
python3 ./ai-assistant/scripts/vector_memory.py sync
python3 ./ai-assistant/scripts/vector_memory.py query "auth retry policy"
```

## Key files

- [AGENTS.md](AGENTS.md): main rules, routing, memory protocol
- [CLAUDE.md](CLAUDE.md): Claude entry point
- [.claude-plugin/plugin.json](.claude-plugin/plugin.json): Claude Code plugin manifest
- [.claude-plugin/marketplace.json](.claude-plugin/marketplace.json): GitHub-installable plugin marketplace catalog
- [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md): first-run setup
- [skill-index.json](skill-index.json): machine-readable skill index
- [`ai-assistant/skills/`](ai-assistant/skills): local expert workflows
- [`ai-assistant/memory/`](ai-assistant/memory): shared durable memory
- [`ai-assistant/scripts/project-understanding.sh`](ai-assistant/scripts/project-understanding.sh): refresh project understanding safely
- [`mcp-configs/README.md`](mcp-configs/README.md): optional MCP setup guide

## Customize

- Edit `ai-assistant/memory/agent-profile.md` for company/product context
- Edit `ai-assistant/memory/project-understanding.md` for repo understanding
- Add or remove skills under `ai-assistant/skills/`
- Adjust rules in `AGENTS.md`, `.claude/rules/`, `.cursor/rules/`, and `.codex/`

## License

MIT — see [LICENSE](LICENSE).
