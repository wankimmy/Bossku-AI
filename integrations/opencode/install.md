# Install BosskuAI in OpenCode

1. Vendor Bossku-AI Markdown policy files (`agents/`, `memory/`, `AGENTS.md`, `playbooks/token-saving.md`).
2. Import [`integrations/opencode/rules.md`](rules.md) into your OpenCode “system prompt” or rules JSON (tool-specific UX).
3. Ensure repository root **`AGENTS.md`** is synced so teammates share one contract surface.
4. Wire optional CLI wrappers under `scripts/bosskuai` onto PATH for route/memory consistency.
5. Smoke test:
   - Ask model to summarize workflow using indicator only (should comply).
   - Run `./scripts/bosskuai route "example prompt"` beside manual planning.
