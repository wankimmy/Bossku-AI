---
name: bosskuai-customize-bosskuai
description: "Activates only when editing BosskuAI's own configuration - AGENTS.md, skills/skill-index.json, skills/*/SKILL.md, agents/*.md, or the host plugin manifests - and injects the real schemas, paths, and verification commands so the model doesn't guess."
license: MIT
metadata:
  author: bosskuai
  source: opencode-customize-opencode
---

# Customize BosskuAI Skill

Activates **only** when editing BosskuAI's own configuration. It carries the real
schemas so you don't invent structure the loader won't read.

## When to Load

- `AGENTS.md` — the shared contract for Claude Code, Cursor, Codex, OpenCode
- `skills/skill-index.json` — generated routing index (never hand-edit)
- `skills/*/SKILL.md` — skill definitions
- `skills/aliases.json`, `skills/vendored.json` — alias and upstream-pack registries
- `agents/*.md` — agent contracts (orchestrator, planner, executor, auditor, final-reviewer)
- `bossku/*.py` — the CLI (install, routing, validation)
- `.claude-plugin/`, `.cursor-plugin/`, `.codex-plugin/`, `.agents/`, `.opencode/` — host manifests

## Repo layout (authoritative)

BosskuAI is a **Python CLI plus a skill library**. There is no Laravel app, no
`ai-assistant/` tree, and no `scripts/` directory - older ADRs under `references/`
describe a previous layout and are history, not instructions.

```
AGENTS.md            CLAUDE.md (must contain a bare @AGENTS.md line)
agents/              five required contracts
bossku/              CLI: cli, install, skills, index, validate, memory, doctor
skills/<id>/SKILL.md one folder per skill
skills/skill-index.json
tests/               unittest suite
```

## SKILL.md frontmatter

Only `name` and `description` are read by every host on every session, so the
description is a **shared context budget** - keep it tight and specific.

```yaml
---
name: <must equal the folder name>
description: "What it does + when to use it. 40-1200 chars."
license: MIT              # optional
allowed-tools: Read, Edit # optional; never `tools:` in a skill
metadata:                 # optional
  author: ...
---
```

Rules:

- `name` must equal the directory name.
- Never use `tools:` in a SKILL.md - that key is for agent contracts. Skills use `allowed-tools:`.
- Keep block scalars simple (`>` or `>-`); both parse, but plain quoted strings are safest.
- Do **not** add `triggers:`/`keywords:` to frontmatter. Routing data lives in the
  index so it costs zero always-loaded context.

## Agent contract (agents/*.md)

```markdown
---
name: <agent-name>
description: <one line>
tools: ["Read", "Grep", "Glob"]
model: <opus|coding|fast|reasoning|review>
---
# <Agent Name> Agent

<one-paragraph runtime behavior>

<!-- runtime-core:start --> ... <!-- runtime-core:end -->

## Contract
## Output
```

## skill-index.json

**Generated - never hand-edit.** Rebuild with `bossku skills index`. Entry shape:

```json
{
  "skills": {
    "skill-id": {
      "path": "skills/skill-id/SKILL.md",
      "name": "skill-id",
      "description": "...",
      "triggers": ["curated phrases"],
      "phrases": ["scraped from the description"],
      "keywords": ["idf-scored terms"],
      "model_role": "coder|planner|reviewer|researcher",
      "pack": "bossku|marketingskills|superpowers|..."
    }
  }
}
```

To improve routing for a skill, add wording to `CURATED_TRIGGERS` in
[`bossku/index.py`](../../bossku/index.py) and rebuild - curated triggers outrank
phrases scraped from descriptions. The index stores a `fingerprint` of every
skill's id + frontmatter; `bossku validate` fails when it drifts.

## Rules When Editing Config

1. **Never remove the `[BOSSKUAI]` indicator** from AGENTS.md.
2. **Every agent contract keeps its `runtime-core` block** — that is the injected behavior.
3. **`name` + `description` are mandatory** in every SKILL.md, and `name` matches the folder.
4. **Adding or renaming a skill requires `bossku skills index`**, or validation fails.
5. **Vendored skills belong to upstream.** Anything listed in `skills/vendored.json` should
   not be reworded locally - a re-vendor will overwrite it. Route around it with curated
   triggers instead.
6. **Adding an agent** requires updating `agents/`, the plugin manifests that list agents,
   and the orchestrator's flow table if it participates in a workflow.
7. **Version bumps must stay in lockstep** — `pyproject.toml` and all three plugin
   manifests are checked for equality.
8. **Never commit secrets** into skills, memory, or manifests.

## Verification

```bash
python -m bossku skills index --root .   # if any skill changed
python -m bossku validate --root .
python -m unittest discover -s tests
```

`validate` checks required files, agent contracts, frontmatter (name/description
present, length bounds, unknown keys), alias targets, index freshness, and that all
four host manifests agree on version and paths. If it fails, the change broke a
contract - fix before declaring done.
