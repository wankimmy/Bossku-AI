# BosskuAI

BosskuAI is a **local AI cofounder layer** for developers.

It works with:

* Claude Code
* Cursor
* Codex

It keeps your **memory, rules, and workflow consistent across all tools**.

---

## What BosskuAI does

* Remembers your project decisions
* Works across multiple AI tools
* Reduces AI fluff, gives structured answers
* Helps you plan, build, and decide like a cofounder
* Uses smart model routing (powerful + cheaper models)

---

## What it is NOT

* Not a SaaS platform
* Not magic AI
* Not replacing human thinking
* Not guaranteed cheaper every time

---

# Install BosskuAI

You can install in **2 ways**

---

## Option 1 — Command Line (Recommended)

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /your/project --profile core
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\your\project -Profile core
```

---

## Option 2 — UI (Dashboard)

Run:

```bash
python3 scripts/dashboard.py
```

Open:

```text
http://127.0.0.1:8765
```

Steps:

1. Go to **Actions tab**
2. Click **“Sync skills to project”**
3. Review (dry-run)
4. Confirm install

---

## Option 3 — Cursor IDE plugin (marketplace manifest)

BosskuAI ships **two plugin systems** side by side:

* **Claude Code** — [`.claude-plugin/`](.claude-plugin/) (`marketplace.json`, `plugin.json`)
* **Cursor** — [`.cursor-plugin/marketplace.json`](.cursor-plugin/marketplace.json) pointing at [`plugins/bossku-ai/`](plugins/bossku-ai/) ([`plugins/bossku-ai/.cursor-plugin/plugin.json`](plugins/bossku-ai/.cursor-plugin/plugin.json))

Cursor follows the [multi-plugin repo layout](https://cursor.com/docs/reference/plugins.md): marketplace at `/.cursor-plugin/`, bundle under `plugins/<name>/` with its own `.cursor-plugin/plugin.json`. This repo aliases shared trees (`agents`, [`ai-assistant`](ai-assistant), rules) via symlinks under `plugins/bossku-ai/` so manifests stay relative with no parent-segment paths.

Until this layout is merged on GitHub, Cursor may still clone `main` missing `.cursor-plugin/` and fall back to [`.claude-plugin/marketplace.json`](.claude-plugin/marketplace.json)—that Claude-only `source` shape triggers installer errors (**gitPath / unsafe source path**). Use a checkout that includes `.cursor-plugin/`, refresh the marketplace, or install locally below.

**Team Marketplace:** Dashboard → Settings → Plugins → import this repo’s Git URL (Teams / Enterprise).

**Local dev:** point Cursor at the **bundle directory**, not repo root ([docs](https://cursor.com/docs/plugins)):

```bash
mkdir -p ~/.cursor/plugins/local
ln -sf /path/to/Bossku-AI/plugins/bossku-ai ~/.cursor/plugins/local/bossku-ai
```

---

## After Install (Important)

Run:

```bash
bash scripts/check-workspace.sh . --profile full
python3 scripts/eval_workspace.py
```

---

## Test it

Open your AI tool and type:

```text
bossku build simple auth system with laravel
```

or

```text
bossku what should i build for my saas
```

---

## If working correctly

You will see:

* structured answers
* clear decisions
* tradeoffs explained
* next steps

---

# How it works (simple)

Every task follows:

```text
1. Read memory
2. Plan (strong model)
3. Execute (cheaper model)
4. Audit (strong model)
5. Save memory
```

---

# Memory (Important)

BosskuAI stores memory locally inside your project.

Types of memory:

* decisions
* plans
* bugs
* learning

Search memory:

```bash
python3 ai-assistant/scripts/auto_memory.py query "what did we decide?"
```

Save memory:

```bash
python3 ai-assistant/scripts/auto_memory.py remember --kind durable "Use PostgreSQL"
```

---

# Multi-step tasks (Advanced)

For harder work (Claude Code):

```text
/audit      → find issues
/decide     → make decision
/implement  → write + review code
```

---

# Why use BosskuAI

* AI forgets → BosskuAI remembers
* AI tools are separate → BosskuAI unifies
* AI answers are generic → BosskuAI structures them

---

# Honest limitations

* Memory only works if useful data is saved
* Cross-tool sharing uses files (not magic sync)
* Deep analysis costs more tokens
* Still need human judgment

---

# Version

v1.9.5

---
