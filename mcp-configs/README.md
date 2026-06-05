# MCP Configs

MCP servers add tools to AI coding assistants. They can provide browser automation, web search, fresh docs, GitHub access, and local code graph analysis.

You do not need MCPs to start BosskuAI. Add them when a task needs live research, browser testing, GitHub work, or library documentation lookup.

## Recommended Servers

Keep only the servers you need active. Three to five active MCPs is usually enough.

| Server | Use it for | API key |
|---|---|---|
| Playwright | Browser automation and UI smoke checks | No |
| Context7 | Current library and framework docs | No |
| Exa | Web search and market research | Yes |
| Firecrawl | Structured page scraping | Yes |
| GitHub | PRs, issues, and repo management | Yes |
| code-review-graph | Local code graph and review support | No |

## API Keys

Set keys in your shell, assistant config, or a secrets manager. Do not commit them.

```bash
export EXA_API_KEY="your-key"
export FIRECRAWL_API_KEY="your-key"
export GITHUB_PAT="your-token"
```

On Windows PowerShell:

```powershell
$env:EXA_API_KEY="your-key"
$env:FIRECRAWL_API_KEY="your-key"
$env:GITHUB_PAT="your-token"
```

## Claude Code

1. Open `~/.claude.json`.
2. Merge the contents of `claude-mcp-servers.json` into the `mcpServers` object.
3. Restart Claude Code.
4. Run `/mcp` to confirm the servers are loaded.

## Cursor

1. Open Cursor settings.
2. Go to Features, then MCP Servers.
3. Add the servers you need using `cursor-mcp-servers.json`.
4. Add API keys in the environment section for each server that needs one.
5. Toggle servers on or off as your work changes.

## Codex

1. Open or create `.codex/config.toml` in your project.
2. Copy the relevant blocks from `codex-mcp-config.toml`.
3. Uncomment only the servers you need.
4. Add API keys in each server `env` table.
5. Restart Codex.

## Suggested Sets

| Work | Activate |
|---|---|
| Normal coding | Context7, GitHub |
| UI testing | Playwright |
| Market research | Exa, Firecrawl |
| Risky refactor | code-review-graph, GitHub, Context7 |
| Launch prep | Exa, Firecrawl, GitHub |
