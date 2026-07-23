---
name: dcg
description: "Destructive Command Guard (dcg) — agent hook that blocks dangerous shell/git before execution. Use when installing or configuring command safety hooks, explaining blocked destructive commands (rm -rf, git reset --hard, force-push), enabling security packs, or hardening Cursor/Claude Code/Codex agent workflows."
---

# DCG — Destructive Command Guard

Thin Bossku skill for [Dicklesworthstone/destructive_command_guard](https://github.com/Dicklesworthstone/destructive_command_guard). The Rust binary is **not** bundled in BosskuAI; install it on the user machine.

A high-performance hook for AI coding agents that intercepts destructive commands *before* they run, with clear denials and safer alternatives. Supports Claude Code, Cursor, Codex CLI, Gemini CLI, Copilot, Hermes, Grok, and related hosts.

## When to use

- User wants agent shell/git safety or asks about `dcg` / Destructive Command Guard
- Commands like `git reset --hard`, `git clean -fd`, `rm -rf`, or `git push --force` are blocked or need explaining
- Enabling database/k8s/cloud packs or tuning `~/.config/dcg/config.toml`
- Hardening multi-agent workflows against accidental data loss

## Install (user machine)

Linux / macOS / WSL:

```bash
curl -fsSL "https://raw.githubusercontent.com/Dicklesworthstone/destructive_command_guard/main/install.sh?$(date +%s)" | bash -s -- --easy-mode
```

Windows (PowerShell):

```powershell
& ([scriptblock]::Create((irm "https://raw.githubusercontent.com/Dicklesworthstone/destructive_command_guard/main/install.ps1"))) -EasyMode -Verify
```

Easy mode installs the binary, updates PATH, and configures detected agent hooks. Prefer the installer over building from source unless the user asks for cargo nightly.

## What it blocks (core)

| Command | Reason |
|---------|--------|
| `git reset --hard` / `--merge` | Destroys uncommitted changes |
| `git checkout -- <file>` / `git restore` (worktree) | Discards modifications |
| `git clean -f` | Deletes untracked files |
| `git push --force` / `-f` | Overwrites remote history |
| `git branch -d` / `-D` / force renames | Deletes or force-overwrites branch refs |
| `git stash drop` / `clear` | Destroys stashes |
| `rm -rf` outside temp dirs | Recursive forced deletion |

## What it allows

Safe git read/write (`status`, `log`, `diff`, `add`, `commit`, `push`, `pull`, `fetch`, `stash` / `pop` / `list`), `git checkout -b`, `git restore --staged`, `git clean -n`, `rm -rf` under `/tmp` / `$TMPDIR`, and `git push --force-with-lease`.

Unrecognized commands are **allowed by default** (fail-open for unknown patterns). Safe patterns are checked before destructive ones.

## Packs and env

Enable packs in `~/.config/dcg/config.toml` or via env:

```toml
[packs]
enabled = ["database.postgresql", "containers.docker", "kubernetes"]
```

| Variable | Effect |
|----------|--------|
| `DCG_PACKS` | Comma-separated packs to enable |
| `DCG_DISABLE` | Packs to disable |
| `DCG_BYPASS=1` | Escape hatch (bypass entirely) |

## Agent behavior

- If `dcg` blocks a command, do **not** invent workarounds that re-run the same destruction. Ask the user; suggest safer alternatives (`git stash`, `--force-with-lease`, `git clean -n`).
- Truly needed destructive ops: have the user run them manually in a separate terminal after a conscious decision.
- Test manually: `echo '{"tool_name":"Bash","tool_input":{"command":"git reset --hard"}}' | dcg` (exit `2` = blocked, `0` = allow).

## Threat model

Protects against well-intentioned but fallible agents. Does **not** stop malicious actors, non-shell file writes, or commands inside scripts the hook does not see.

## License

Upstream: MIT **with OpenAI/Anthropic rider**. See [LICENSE](https://github.com/Dicklesworthstone/destructive_command_guard/blob/main/LICENSE). BosskuAI vendors this skill documentation only; the binary remains upstream.
