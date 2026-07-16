# Claude Code Setup Checklist

> If the request is general, ambiguous, or touches many files, ask clarifying yes/no questions before acting.

- Are `CLAUDE.md`, `.claude/rules/`, `.claude/commands/`, and shared `AGENTS.md` responsibilities clear and non-contradictory?
- Are MCPs recommended only when they materially improve the repo workflow?
- Are hooks advisory by default, with mutation or remote actions requiring explicit user intent?
- Are approval boundaries clear for installs, remote writes, deployments, destructive actions, DB access, and secrets?
- Are model names, commands, paths, and setup steps current for this repo?
- Are tokens and private local paths absent from committed config?
- Are longer details linked from shared references instead of duplicated in startup files?
