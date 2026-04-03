# Contributing

Thanks for contributing.

## Principles

- Keep the repo portable. Do not introduce machine-specific absolute paths.
- Prefer relative references throughout the starter.
- Keep skills concise and reusable.
- Prefer adding high-signal guidance over adding more volume.
- Treat planning-first, triple-checking, and asking when material facts are unconfirmed as non-negotiable behavior.

## What to contribute

Good contributions include:

- better skills
- stronger checklists and playbooks
- clearer onboarding docs
- domain packs that are optional and well scoped
- example prompts and example outputs
- portability and consistency fixes across Codex, Claude, and Cursor

## Skill format

Every new skill file must follow this structure:

```markdown
---
name: bosskuai-<slug>
description: One-line description under 200 characters. Used by routers.
---

# BosskuAI <Title>

## How this differs from nearby skills
## Mindset
## <Domain lenses or workflow sections>
## Guardrails
## Output format
## References
```

- `name` must match the folder slug exactly (`bosskuai-<slug>`)
- `description` is loaded by routers — keep it specific and under 200 chars
- `Guardrails` must include the standard ambiguity protocol bullet
- When adding a skill, also update `AGENTS.md`: skill roster table, quick reference table, local skills table, proactive skill use, and phased pipelines

## Before opening a PR

Check that:

- docs match the actual files in the repo
- no absolute local paths remain
- new skills have clear descriptions and a focused workflow
- changes do not contradict `AGENTS.md`
- examples are practical, not just aspirational

## Style guidance

- Prefer short, direct language
- Separate confirmed facts from inference
- Avoid hype
- Keep templates and examples easy to copy

## Public repo quality bar

- planning-first for meaningful tasks
- triple-check important conclusions
- ask instead of guessing when something material is unconfirmed
- keep durable memory reusable across tool surfaces
