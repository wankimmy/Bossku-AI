# BosskuAI Maintenance Scripts

Lightweight scripts for continuous learning, skill stocktake, and rules distillation. Inventory-first — no automatic deletions, rule edits, or silent side effects.

## Scripts

- `scan-skills.sh` — inventory local skills
- `scan-commands.sh` — inventory local Claude command files
- `scan-rules.sh` — inventory rule and instruction files
- `learning-doctor.sh` — check learning hygiene: stale memory, empty high-value files, consumed continuation state
- `relearn-project-understanding.sh` — snapshot project understanding and generate a refresh prompt
- `skill-stocktake.sh` — combined inventory for skills, commands, and references
- `rules-distill-context.sh` — combined rules-distillation context inventory

## Usage

Run from the workspace root:

```bash
bash ./ai-assistant/scripts/learning-doctor.sh
bash ./ai-assistant/scripts/relearn-project-understanding.sh
bash ./ai-assistant/scripts/skill-stocktake.sh
bash ./ai-assistant/scripts/rules-distill-context.sh
```

Or pass an explicit root:

```bash
bash ./ai-assistant/scripts/learning-doctor.sh /path/to/workspace
```
