# Playbook: Repo cleanup

**Goal:** reduce drift, duplicate rules, and dead skills without destabilizing installs.

**Skills**

- `bosskuai-rules-distill` — compress rules safely
- `bosskuai-skill-stocktake` — overlap and deprecation passes

**Deep playbooks**

- [`../ai-assistant/references/playbooks/rules-distillation-playbook.md`](../ai-assistant/references/playbooks/rules-distillation-playbook.md)
- [`../ai-assistant/references/playbooks/skill-stocktake-playbook.md`](../ai-assistant/references/playbooks/skill-stocktake-playbook.md)

**Workflow**

1. Dry-run inventory (skills, hooks, MCP config).  
2. Propose deletions/changes **one merge unit at a time**.  
3. Run `scripts/validate-skill-index.sh` after `skill-index.json` edits.
