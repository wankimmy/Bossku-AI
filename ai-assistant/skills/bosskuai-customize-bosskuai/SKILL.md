---
name: bosskuai-customize-bosskuai
description: "Activates only when editing BosskuAI's own configuration (AGENTS.md, skill-index.json, app/config/bossku_models.php, agents/*.md) and injects the real schemas + contract rules so the model doesn't guess."
license: MIT
metadata:
  author: bosskuai
  source: opencode-customize-opencode
---

# Customize BosskuAI Skill

This skill activates **only** when you are editing BosskuAI's own configuration files. It injects the real schemas and contract rules so you don't guess at the structure.

## When to Load

Load this skill when the task touches any of:

- `AGENTS.md` — the shared contract for Claude Code, Cursor, Codex, OpenCode
- `skill-index.json` — the skill registry
- `agents/*.md` — agent contracts (orchestrator, planner, executor, auditor, etc.)
- `app/config/bossku_models.php` — model routing configuration
- `app/config/bossku.php` — main configuration
- `ai-assistant/config/model-router.yaml` — workspace model hints
- `ai-assistant/skills/*/SKILL.md` — skill definitions
- `ai-assistant/references/*.md` — reference documents

## What This Skill Provides

### AGENTS.md structure

```markdown
# BosskuAI Workspace Layer

## Mandatory response indicator
[BOSSKUAI] prefix block — every response must begin with it.

## Activation
- The standalone word `bossku` activates BosskuAI mode.
- Load named skills first.
- Trivial tasks: skip heavy routing.

## Default discipline
- Ponytail (lazy senior dev) — always on
- Taste (anti-slop) — always on
```

### Agent contract structure (agents/*.md)

```markdown
---
name: <agent-name>
description: <one line>
tools: ["Read", "Grep", "Glob", ...]
model: <fast|coding|reasoning|review>
---
# <Agent Name> Agent

## Runtime core
<one-paragraph runtime behavior>

## Prefix
[BOSSKUAI] indicator block

## Contract
<numbered steps>

## Output
<structured return fields>
```

### skill-index.json entry shape

```json
{
  "skills": {
    "skill-name": {
      "path": "ai-assistant/skills/skill-name/SKILL.md",
      "description": "...",
      "triggers": ["keyword1", "keyword2"],
      "model_role": "coder|planner|reviewer|researcher"
    }
  }
}
```

### bossku_models.php structure

```php
return [
    'router' => env('BOSSKU_ROUTER_MODEL', 'kimi-k2.6'),
    'orchestrator' => env('BOSSKU_ORCHESTRATOR_MODEL', 'kimi-k2.6'),
    'executor' => [
        'default' => ['primary' => env('BOSSKU_EXECUTOR_DEFAULT_MODEL', 'glm-5.1'), 'fallback' => [...]],
        'high_risk' => ['primary' => env('BOSSKU_EXECUTOR_HIGH_RISK_MODEL', 'deepseek-v4-pro'), 'fallback' => [...]],
    ],
    'auditor' => env('BOSSKU_AUDITOR_MODEL', 'deepseek-v4-pro'),
    'final_reviewer' => env('BOSSKU_FINAL_REVIEWER_MODEL', 'kimi-k2.6'),
];
```

## Rules When Editing Config

1. **Never remove the `[BOSSKUAI]` indicator** from AGENTS.md — every response must begin with it.
2. **Every agent contract must have a `## Runtime core` block** — this is the runtime-injected behavior.
3. **Skill frontmatter must have `name` and `description`** — these are what the skill router reads.
4. **Model roles are: `fast`, `coding`, `reasoning`, `review`** — don't invent new ones without updating the model-router.
5. **Adding a skill requires updating `skill-index.json`** — the registry is the source of truth for available skills.
6. **Adding an agent requires updating `agents/` + the orchestrator's Flows table** if the agent participates in a workflow.
7. **Config changes must keep test env clean** — `phpunit.xml` forces `BOSSKU_*` env vars; don't hardcode secrets.

## Verification

After editing any BosskuAI config file, run:

```bash
bash ./scripts/check-workspace.sh
bash ./scripts/validate-skill-index.sh
cd app && php artisan test
```

If any of these fail, the config change broke a contract — fix before declaring done.