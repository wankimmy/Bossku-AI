# MCP Server Configuration Guide

## What are MCPs and why they matter

Model Context Protocol (MCP) servers extend AI coding assistants with real-time capabilities they don't have out of the box: live web search, browser automation, fresh library docs, and repo management. Without MCPs, Claude Code, Cursor, and Codex work only from their training data and your local files. With MCPs, they can research competitors, verify documentation, automate browser tests, and interact with GitHub — all inline during a session.

For a startup, the practical payoff is: less tab-switching, fewer hallucinated API signatures, and agents that can gather market data or run QA flows without you copying and pasting.

**Recommendation: keep 3–5 MCPs active at any time.** Too many MCPs slow tool selection and increase token overhead. Pick the ones that match your current phase of work. For PR review or refactor-heavy work, temporarily add `code-review-graph` as a local graph MCP.

---

## Priority servers

| Server | Best for | API key required |
|--------|----------|-----------------|
| Playwright | Browser automation, QA, competitor UI monitoring | No |
| Exa | Neural web search, market research, real-time data | Yes — free tier |
| Context7 | Live library/framework documentation lookup | No |
| Firecrawl | Web scraping, competitor pricing, structured page data | Yes — free tier |
| GitHub | PR review, issue triage, repo management | Yes — GitHub PAT |
| code-review-graph | Local blast-radius analysis, graph-aware PR review, call/test tracing | No |

---

## Required API keys

### Exa
- Sign up at [exa.ai](https://exa.ai) — free tier includes 1,000 searches/month
- Copy your API key from the dashboard
- Set env var: `export EXA_API_KEY="your-key-here"` (add to `~/.zshrc` or `~/.bashrc`)

### Firecrawl
- Sign up at [firecrawl.dev](https://firecrawl.dev) — free tier includes 500 credits/month
- Copy your API key from the dashboard
- Set env var: `export FIRECRAWL_API_KEY="your-key-here"`

### GitHub Personal Access Token (PAT)
- Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
- Generate a token with scopes: `repo`, `read:org`, `read:user`
- Set env var: `export GITHUB_PAT="your-token-here"`

> Store all API keys in your shell profile or a secrets manager. Never commit them to a repo.

### code-review-graph
- Requires Python 3.10+.
- Fastest setup: `pipx install code-review-graph` or `pip install code-review-graph`.
- If you use `uv`, the MCP config can run through `uvx code-review-graph serve`.
- Build the graph once per target repo: `code-review-graph build`.
- Generated graph data stays local in `.code-review-graph/`; do not commit it.

---

## Per-tool setup

### Claude Code (`~/.claude.json`)

1. Open `~/.claude.json` (create it if it doesn't exist — it's a JSON object)
2. Add or merge the contents of `claude-mcp-servers.json` into the `mcpServers` key:

```json
{
  "mcpServers": {
    // paste the contents of claude-mcp-servers.json here
  }
}
```

3. Make sure your API key env vars are exported before launching Claude Code (or add them to `~/.zshrc`)
4. Restart Claude Code. Verify with `/mcp` — you should see the configured servers listed.

**Selective activation tip:** You can comment out servers in the JSON by wrapping them in a `_disabled` key prefix (e.g. `"_playwright": {...}`) when you don't need them, then rename back when you do.

---

### Cursor (Settings UI)

1. Open Cursor → Settings → Features → MCP Servers
2. Click "Add MCP Server" for each server you want
3. Use the values from `cursor-mcp-servers.json` — paste the command and args for each server
4. For servers requiring API keys, add them in the "Environment Variables" section of each server entry
5. Toggle servers on/off from the same UI without deleting the config

**Note:** If you have the Pencil extension installed in Cursor, it already covers browser automation. Playwright is optional in Cursor — skip it to reduce overhead.

---

### Codex (`.codex/config.toml`)

1. In your project root, create or edit `.codex/config.toml`
2. Copy the contents of `codex-mcp-config.toml` into the file
3. Uncomment the `[mcp_servers.<name>]` blocks for the servers you want active
4. Add API key values directly in the `env` table for each server (or reference shell env vars using `${VAR_NAME}` syntax if your Codex version supports it)
5. Restart Codex / reload the config

> Codex config is per-project. This is useful for scoping MCPs to specific repos — e.g. enable GitHub MCP only in repos where you're doing active PR work.

---

## Which MCPs to activate by phase

| Work phase | Recommended active MCPs |
|------------|------------------------|
| Market research / competitive analysis | Exa, Firecrawl |
| Feature development | Context7, GitHub |
| Code review / risky refactor | GitHub, code-review-graph, Context7 when library behavior matters |
| QA / testing | Playwright |
| Launch prep | Exa, Firecrawl, GitHub |
| General coding | Context7, GitHub |

Activate only what you need. Deactivate when switching phases to keep latency low.
