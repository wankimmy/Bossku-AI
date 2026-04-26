# Plugin Testing

Run these checks before publishing a release.

```bash
bash scripts/check-workspace.sh . --profile full
bash scripts/verify-skill-references.sh .
bash scripts/validate-skill-index.sh .
python3 -S scripts/eval_workspace.py
```

Then test Claude Code plugin loading locally:

```bash
claude --plugin-dir . --debug
/plugin validate
/reload-plugins
```

Manual checks:

- Skills appear with the expected names.
- Commands are visible.
- Agents appear under `/agents`.
- Hooks do not run unless explicitly enabled.
- `plugin.with-hooks.json` is used only for hook-enabled testing.

Current limitation: repository scripts can validate file layout, JSON consistency, and local evals. They cannot prove Claude Code runtime loading without running Claude Code itself.
