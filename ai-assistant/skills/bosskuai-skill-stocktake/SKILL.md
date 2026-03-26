---
name: bosskuai-skill-stocktake
description: Use this to audit local skills, commands, and nearby guidance for overlap, staleness, weak triggers, and missing maintenance improvements.
---

# BosskuAI Skill Stocktake

Use this skill when the workspace has accumulated enough skills or commands that quality drift, overlap, or stale guidance is becoming a risk.

## Workflow

1. Inventory the local skills, commands, and related guidance in the current workspace before judging any single file.
2. Check each item for clarity of trigger, uniqueness, actionability, overlap, and maintenance cost.
3. Compare the skill content against `AGENTS.md`, shared memory, references, and nearby commands so duplicated guidance is not counted as unique value.
4. Flag stale, thin, overlapping, or weakly scoped items, but do not delete or merge anything silently.
5. Recommend a verdict for each item: keep, improve, merge, update, or retire.
6. For merge or retire recommendations, name the replacement target or stronger artifact that already covers the need.
7. End with the smallest safe maintenance actions instead of trying to rewrite the whole system at once.

## Output expectation

- scope audited
- items reviewed
- keep / improve / merge / update / retire recommendations
- reasons grounded in overlap, quality, or maintenance cost
- highest-value next maintenance actions

## References

- `../../references/checklists/skill-health-checklist.md`
- `../../references/playbooks/skill-stocktake-playbook.md`
- `../../references/checklists/learning-promotion-checklist.md`
