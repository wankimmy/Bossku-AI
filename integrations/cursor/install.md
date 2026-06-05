# Install BosskuAI in Cursor

1. Clone or vendor BosskuAI into your workspace (or symlink `Bossku-AI` into monorepo root).
2. Copy [`integrations/cursor/rules.md`](rules.md) content into **Project Rules** (`Cursor Settings → Rules` or `.cursor/rules/bosskuai.mdc`).
3. Ensure **`AGENTS.md`** from BosskuAI is at your **workspace repo root**, or symlink it.
4. Copy or merge **`skill-index.json`**, **`ai-assistant/`** tree as needed (`scripts/install.sh` / `install.ps1` automates common profiles).
5. Point rules at skills: Cursor reads `.mdc` files — keep **`alwaysApply`** only for the Bossku snippet if you multi-stack rules.
6. Test with sample prompts (see [`../../docs/examples.md`](../../docs/examples.md)).

Upstream reference file in repo: `.cursor/rules/bosskuai.mdc`.
