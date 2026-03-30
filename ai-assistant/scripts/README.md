# BosskuAI Maintenance Scripts

These scripts support deterministic collection for BosskuAI maintenance workflows.

Use them when running:

- continuous learning
- skill stocktake
- rules distillation

They are intentionally lightweight:

- inventory first
- no automatic deletions
- no silent rule edits
- no persistent learning side effects

## Scripts

- `scan-skills.sh` — inventory local BosskuAI skills
- `scan-commands.sh` — inventory local Claude command files
- `scan-rules.sh` — inventory rule and instruction files used as shared guidance
- `learning-doctor.sh` — check learning hygiene: stale memory, stale counts, empty high-value memory files, and consumed continuation state
- `skill-stocktake.sh` — combined stocktake inventory for skills, commands, and references
- `rules-distill-context.sh` — combined rules-distillation context inventory

## Usage

Run from the workspace root:

```bash
bash ./ai-assistant/scripts/learning-doctor.sh
bash ./ai-assistant/scripts/skill-stocktake.sh
bash ./ai-assistant/scripts/rules-distill-context.sh
```

Or pass an explicit repo root:

```bash
bash ./ai-assistant/scripts/learning-doctor.sh /path/to/workspace
bash ./ai-assistant/scripts/skill-stocktake.sh /path/to/workspace
bash ./ai-assistant/scripts/rules-distill-context.sh /path/to/workspace
```
