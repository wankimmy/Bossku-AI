# Agent Security Hardening Checklist

- Are the agent’s tools, permissions, and sandbox level scoped to the minimum needed?
- Are external links, fetched docs, MCP outputs, and third-party content treated as untrusted by default?
- Could a prompt injection in docs, chat logs, or tool output change agent behavior dangerously?
- Are secrets, credentials, and sensitive files protected from unnecessary read or write access?
- Are memory files, reusable skills, rules, and hooks protected from poisoning or unsafe persistence?
- Are new integrations, channels, MCP servers, or automations justified by real need?
- Are dangerous commands, broad permissions, or networked automations being introduced without review?
- Are suspicious config changes, hidden text, remote instructions, or unsafe examples called out?
- If the repo uses agent automations, are there safe defaults, review steps, and rollback paths?
- Are time-sensitive security claims or tool capabilities being verified before relying on them?
