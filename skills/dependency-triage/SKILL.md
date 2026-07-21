---
name: dependency-triage
description: >
  Use when scanning package manifests and lockfiles for outdated deps and CVEs,
  grouped by patch/minor/major risk. Triggers: dependency sweep, security updates,
  renovate backlog.
user_invocable: true
---

# Dependency Triage Skill

## Output per package

```markdown
### package-name (ecosystem: npm|pip|go|etc.)
- Current: x.y.z
- Suggested: x.y.z
- Risk: patch | minor | major
- CVE: none | CVE-XXXX (severity)
- Actionable: yes | no (denylist / human gate)
- Suggested loop action: patch-in-worktree | escalate-human | skip
```

## Classification Rules

- **patch**: semver patch or lockfile-only security fix with no API change
- **minor**: semver minor — cautious, verifier required
- **major**: always escalate-human unless explicitly pre-approved in state
- **denylist**: packages in state denylist → escalate-human, no auto-touch
- **high-severity CVE**: escalate if fix requires major or breaking change

## Rules

- Prefer the smallest safe bump that resolves the advisory.
- Never bundle unrelated package updates in one change.
- Record human overrides from `dependency-sweeper-state.md` every run.
- If lockfile conflict or peer dependency warning → escalate-human.