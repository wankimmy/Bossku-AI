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
* **Cursor** — [`.cursor-plugin/marketplace.json`](.cursor-plugin/marketplace.json) with `"source": "./"` and [`.cursor-plugin/plugin.json`](.cursor-plugin/plugin.json) (same pattern as official single-repo plugins such as Cloudflare: plugin root is the repository root; no symlinked plugin bundles).

Until `main` on GitHub includes `.cursor-plugin/`, Cursor’s cached clone may only see [`.claude-plugin/marketplace.json`](.claude-plugin/marketplace.json)—that Claude-only `source` shape triggers **gitPath / unsafe source path**. Fix: use this checkout, **or** remove the GitHub marketplace entry and install locally only, **or** merge and re-add the repo after publish.

**Team Marketplace:** Dashboard → Settings → Plugins → import this repo’s Git URL (Teams / Enterprise).

**Local install** — symlink the **repository root** (not a subfolder):

```bash
mkdir -p ~/.cursor/plugins/local
ln -sf /path/to/Bossku-AI ~/.cursor/plugins/local/bossku-ai
```

**If you still see the error:** remove the stale GitHub sync cache, then reload Cursor:

```bash
rm -rf ~/.cursor/plugins/marketplaces/github.com/wankimmy/bossku-ai
```

If `wankimmy/Bossku-AI` on GitHub is still behind your fixed clone, **do not** re-add that marketplace URL until it is updated, or the bad manifest will be downloaded again.

**Cursor shows “66 skills” but the repo has 80+:** The disk tree and [`skill-index.json`](skill-index.json) list **85** skills. Bossku also marks **19** specialists as **manual-only** in `routing.manual_only_skill_ids` (explicit domain tools like Laravel, Nuxt, GSAP, etc.). **`85 − 19 = 66`** — Cursor’s Plugins UI commonly lists only the subset it treats as broadly auto-routable; the rest remain in the bundle and load when you name them (`/skill …` / prompt). This is intentional routing, not a failed update.

Still do a refresh after `git pull` so indexes match disk:

```bash
git pull   # use your fork/remote if you track updates there
bash scripts/cursor-plugin-refresh.sh
python3 -c 'import json; d=json.load(open("skill-index.json")); print(len(d["skills"]), "total;", len(d["routing"]["manual_only_skill_ids"]), "manual-only")'
```

Then **fully quit Cursor** and reopen (not only Reload Window).

In **Plugins**, remove duplicate Bossku entries (marketplace + local) so you are not mixing an old Marketplace build with `~/.cursor/plugins/local/bossku-ai`.

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
