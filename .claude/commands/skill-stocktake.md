# BosskuAI Skill Stocktake Command

Use this command to audit local skills, commands, and nearby guidance for overlap, weak triggers, stale references, and maintenance cost.

## Intent

- inventory the current expert layer
- surface overlap and drift
- recommend keep / improve / merge / update / retire decisions
- avoid silent deletion or over-pruning

## Instructions

1. Use `bosskuai-skill-stocktake` and the skill health checklist.
2. Review the relevant skills, commands, and linked references in scope.
3. Compare them against `AGENTS.md`, memory, and references to detect overlap.
4. Produce verdicts with reasons grounded in actionability, uniqueness, freshness, and maintenance cost.
5. Do not retire, merge, or delete anything automatically. Present the maintenance recommendations first.

## Output

- scope audited
- items reviewed
- verdicts
- overlap or staleness findings
- smallest safe maintenance actions
