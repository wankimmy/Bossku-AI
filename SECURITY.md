# Security Policy

BosskuAI is a local workspace layer. It can influence how AI coding tools read, write, and reason about a project, so treat configuration changes as code changes.

## Report a Vulnerability

Open a private security advisory or contact the maintainer through GitHub.

## Safety Notes

- Review hook scripts before enabling hooks.
- Do not store secrets in `ai-assistant/memory/`.
- Do not paste production credentials into skill files, memory files, or examples.
- Treat MCP/tool integrations as privileged. Keep them least-privilege.
- Validate generated code with tests, review, and security checks.

## Hooks

Hooks are disabled by default. Enable only after reviewing `.claude/settings.hooks.example.json` and scripts under `ai-assistant/hooks/`.
