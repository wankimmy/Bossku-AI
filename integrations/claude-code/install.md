# Install BosskuAI in Claude Code

1. Vendor BosskuAI into your workspace root (or symlink `CLAUDE.md` + `agents/` paths).
2. Copy [`integrations/claude-code/CLAUDE.md`](CLAUDE.md) sections into **`CLAUDE.md`** at workspace root—or keep root `CLAUDE.md` canonical and symlink.
3. Install rules: replicate `.claude/rules/bosskuai.md` patterns or merge Markdown from [`../cursor/rules.md`](../cursor/rules.md).
4. Optional: `.claude/commands/*.md` for `/plan`, `/route`, Bossku wrappers (ships with Bossku-AI upstream).
5. Ensure **`AGENTS.md`**, **`ai-assistant/`**, and **`skill-index.json`** accompany the project (`scripts/install.sh --profile`).
6. Test memory + routing:
   ```bash
   python3 ai-assistant/scripts/auto_memory.py status
   ./scripts/bosskuai route "sample task description"
   ```
