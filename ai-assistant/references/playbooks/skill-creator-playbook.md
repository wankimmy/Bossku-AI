# Skill Creator Playbook

Use this when creating, improving, or benchmarking a BosskuAI skill.

## Workflow

1. Define the recurring job and why existing skills do not already cover it.
2. Name adjacent skills and write explicit boundaries.
3. Choose the artifact type: skill, checklist, playbook, rule, memory, or no change.
4. Write frontmatter in trigger language with concrete examples.
5. Keep `SKILL.md` as an operating manual, not a knowledge dump.
6. Move longer verification passes or procedures to references.
7. Add guardrails for scope creep, stale assumptions, secrets, unsupported claims, and irreversible actions.
8. Validate with at least four prompts: ideal trigger, adjacent non-trigger, ambiguous request, and high-risk request.
9. Run reference verification and inspect overlap before finalizing.

## Output expectation

- artifact choice and rationale
- skill boundary summary
- changed files
- validation prompts or reason not run
- overlap and reference check result
