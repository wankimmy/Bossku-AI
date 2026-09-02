# Memory and Obsidian

## Project memory

Curated Markdown only, under `.bossku/memory/`:

| File | Purpose |
|---|---|
| `project.md` | Durable project summary |
| `decisions.md` | Decisions worth keeping |
| `plans.md` | Plans and milestones |
| `learnings.md` | Verified lessons |
| `handoff.md` | Ephemeral continuation (clear when done) |

```bash
bossku remember --project . --kind decision "Use toolkit-only main."
bossku sync --project .
```

## Obsidian export

- **One-way** — repo → vault only
- **Dedicated folder** — `<vault>/BosskuAI/<project>/`
- **No raw prompts or transcripts**
- Vault path stored in `~/.bosskuai/config.json` only

If the vault is offline, local memory still saves; run `bossku sync` later.

If a vault file was edited manually after export, BosskuAI writes a `*.conflict.md` copy instead of overwriting.

## Session-end sync hooks (optional)

`bossku remember` already exports on every call. `bossku hooks install` additionally wires
a session-end hook into Claude Code, Cursor, Codex, and OpenCode so `bossku sync` reruns
automatically when a session ends — a safety net, not a new write path. It only touches
tools that are already configured on the machine and only adds to existing hook config,
never replaces it.

```bash
bossku hooks install               # all installed tools
bossku hooks install --tools codex # one tool: claude_code, cursor, codex, opencode
bossku hooks uninstall             # remove only BosskuAI's own entries
```

Codex requires a one-time interactive approval before it will run any hook (its own
trust gate) — the hook is written immediately, but only fires after the user approves it
during one normal Codex session. `bossku doctor` reports which tools currently have the
hook installed.

## Privacy

Secrets are redacted before write. Do not store API keys, passwords, or `.env` contents in memory files.
