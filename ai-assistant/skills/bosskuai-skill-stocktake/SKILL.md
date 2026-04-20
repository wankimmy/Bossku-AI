---
name: bosskuai-skill-stocktake
description: Use this to audit local skills, commands, and nearby guidance for overlap, stale content, weak triggers, and prompt-weight bloat.
---

# BosskuAI Skill Stocktake

Use this skill when the skill system itself needs maintenance.

## Audit dimensions

For each skill, check:

- trigger clarity
- uniqueness vs overlap
- actionability
- freshness
- token efficiency
- output format quality
- guardrails

## Workflow

1. Inventory skill files and note rough size/verbosity.
2. Check root `AGENTS.md` alignment: missing, stale, or duplicate roster entries.
3. Flag skills that are vague, overlapping, stale, or too heavy for how often they load.
4. Prefer the smallest safe maintenance action: keep, improve, merge, update, retire.
5. Prioritize high-traffic skills and always-loaded rule surfaces first.

## Token-efficiency bias

- Repeated routing tables belong in root `AGENTS.md`, not every tool-specific file.
- Keep skill bodies dense with decisions and guardrails; move long procedures to playbooks/checklists when possible.
- A shorter skill is better only if routing clarity and actionability stay intact.

## Output

Return:

- scope audited
- audit result per skill
- overlap map
- priority maintenance actions
- what not to change yet and why

## References

- `../../references/checklists/skill-health-checklist.md`
- `../../references/playbooks/skill-stocktake-playbook.md`
- `../../references/checklists/learning-promotion-checklist.md`
