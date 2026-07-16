# Contributing

BosskuAI is a toolkit-only repo: skills, agents, CLI, and docs.

## Changes

1. Keep `AGENTS.md` tool-neutral and concise.
2. Add or edit skills under `skills/<id>/SKILL.md` with YAML frontmatter.
3. Register deprecated names in `skills/aliases.json` instead of duplicating folders.
4. Run before opening a PR:

```bash
pip install -e .
python -m bossku validate --root .
python -m unittest discover -s tests -v
```

## Archive

Product MVP code lives on `archive/product-mvp-2026-07`. Do not reintroduce Laravel/Nuxt/Docker surfaces on `main` without an explicit decision.
