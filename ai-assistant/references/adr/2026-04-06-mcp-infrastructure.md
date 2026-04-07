# ADR: MCP Infrastructure for bosskuAI

**Date:** 2026-04-06
**Status:** Accepted
**Deciders:** Safwan (cofounder), Claude (AI cofounder)

---

## Context

bosskuAI had no Model Context Protocol (MCP) support. All three AI tools in the stack — Claude Code, Cursor, and Codex — are MCP-capable, but no configuration existed to activate real-time capabilities like web search, browser automation, or live documentation.

During an ecosystem audit (ECC), 30+ MCP servers were evaluated. Categories ranged from database connectors and cloud infra tools to specialized domain servers. For a startup operating with limited attention and API budget, activating many MCPs would create:

- Higher latency per AI response (more tools to evaluate)
- API key sprawl and credential management overhead
- Inconsistent availability across tools (not all MCPs work in all clients)
- Cognitive load from too many options during coding sessions

The constraint was clear: pick a small, high-leverage set that covers the most common startup AI workflows without requiring active management.

---

## Decision

Add MCP configuration **templates** (not auto-install scripts) for 5 priority servers across all three tools.

The 5 servers selected:

| Server | Rationale |
|--------|-----------|
| Playwright | Browser automation and QA — no API key, high utility for competitor monitoring and E2E tests |
| Exa | Neural search quality significantly exceeds standard web search for research tasks |
| Context7 | Eliminates hallucinated API signatures by pulling live docs — no API key, pure upside |
| Firecrawl | Structured web scraping for pricing pages, competitor data, and market research |
| GitHub | Inline PR review, issue triage, and repo management without leaving the AI session |

**Template approach (not auto-install):** Config files are provided as opt-in snippets users copy into their tool configs. This avoids silently adding MCPs to tools users haven't audited, and keeps activation intentional.

**Cross-tool strategy:** Three separate config files are maintained — one per tool — because each tool has a different config format and different MCP availability characteristics. Cursor users with the Pencil extension can skip Playwright. Codex configs are per-project (`.codex/config.toml`) rather than global, enabling repo-scoped MCP activation.

**Advisory integration:** Skills in `ai-assistant/skills/` may recommend specific MCPs when relevant (e.g., "use Exa MCP for this research task") but must gracefully degrade if the MCP is not present — falling back to asking the user to search manually or using training-data knowledge with an explicit caveat.

---

## Alternatives considered

### Auto-install script
Rejected. Running `npm install -g` or patching `~/.claude.json` automatically creates trust and auditability concerns. The user should consciously choose which MCPs to activate.

### Activate all 30+ MCPs
Rejected. Performance degradation is real. Tool selection overhead increases with the number of available tools. Startup focus requires minimalism.

### Single shared config across all tools
Rejected. Claude Code, Cursor, and Codex each have different config file formats and different MCP support characteristics. A single config would either be too generic to be useful or would require per-tool post-processing anyway.

### Use only built-in tool capabilities
Rejected. The capability gap between training-data-only AI and MCP-augmented AI is material for market research, QA, and documentation tasks. Ignoring MCPs would leave significant leverage on the table.

---

## Consequences

### Positive

- **Web research:** Exa + Firecrawl enable inline competitor analysis, pricing research, and market data gathering without leaving the AI session
- **QA automation:** Playwright MCP enables browser-based test flows and competitor UI monitoring from within Claude Code
- **Accurate documentation:** Context7 eliminates a common failure mode (hallucinated library APIs) at zero API cost
- **GitHub integration:** PR review and issue management inline reduces context switching during engineering sprints
- **Startup-appropriate scope:** 5 servers is a manageable set that covers research, engineering, and QA phases

### Negative

- **API key management:** Exa, Firecrawl, and GitHub PAT require users to obtain and manage credentials. Free tiers have usage caps that may run out under heavy use.
- **MCP availability varies across tools:** Not all 5 servers are confirmed to work identically in all three clients. Behavior may differ between Claude Code, Cursor, and Codex MCP implementations.
- **npx cold start latency:** First invocation of any npx-based MCP server downloads the package, adding latency. Subsequent calls are cached.
- **Maintenance burden:** MCP server packages are third-party. Package names or APIs may change, requiring periodic config updates.
- **Opt-in friction:** Template approach means users must manually copy configs. Some users may not activate MCPs at all, reducing the uplift.

---

## Review trigger

Revisit this decision if:
- A significantly better MCP server emerges that displaces one of the 5
- Claude Code, Cursor, or Codex changes its MCP config format
- Free tier limits on Exa or Firecrawl become a recurring operational problem
- A native integration (e.g., Cursor built-in web search) makes an MCP redundant
