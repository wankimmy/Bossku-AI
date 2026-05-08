# Install BosskuAI in Codex

1. Vendor Bossku-AI artifacts into workspace root (**`AGENTS.md`**, **`skill-index.json`**, **`ai-assistant/`**, **`agents/`**, **`integrations/`**, **`packages/bossku-ai/`** recommended).
2. Copy [`integrations/codex/AGENTS.md`](AGENTS.md) → **`.codex/AGENTS.md`** (Codex discovers this convention in many workspaces).
3. Register plugin if using packaged skill: `@bossku-ai` via `packages/bossku-ai` instructions (Codex MCP marketplace flow updates often—consult local Codex docs).
4. Point developers to **`AGENTS.md`** for cross-tool parity.
5. Validate:
   ```bash
   bash ./scripts/check-workspace.sh
   ```
