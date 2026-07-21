---
name: ci-triage
description: >
  Use when classifying CI failures as flake, regression, env, or config before any
  fix attempt. Triggers: red CI, failed workflow, CI sweeper, pipeline failure.
user_invocable: true
---

# CI Triage Skill

## Output per failure

```markdown
### Failure — branch @ sha
- Job / step:
- Error (1-3 lines):
- Classification: flake | regression | env | config
- Actionable: yes | no
- Suggested loop action: minimal-fix | watch | escalate-human
```

## Classification Rules

- **flake**: intermittent, passed on retry, no code change
- **regression**: new failure correlated with recent commit
- **env**: runner, registry, secrets, quota
- **config**: workflow, dependency install, cache

Env failures → escalate-human. Do not "fix" with code changes.