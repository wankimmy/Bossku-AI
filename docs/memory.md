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

## Privacy

Secrets are redacted before write. Do not store API keys, passwords, or `.env` contents in memory files.
