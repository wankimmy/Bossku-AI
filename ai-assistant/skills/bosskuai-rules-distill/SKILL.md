---
name: bosskuai-rules-distill
description: Use this to extract repeated cross-cutting principles from skills and references, then propose safe rule updates instead of letting important guidance stay fragmented.
---

# BosskuAI Rules Distill

Use this skill when the repo has learned useful principles across multiple skills or references and those principles should become stronger shared rules.

## Workflow

1. Read the relevant skills, references, and current rule files before proposing any rule changes.
2. Look for cross-cutting principles that appear in more than one place and clearly change future assistant behavior.
3. Separate principles that belong in rules from details that should stay in skills, playbooks, or checklists.
4. Compare candidate principles against existing rules to avoid adding duplicates with different wording.
5. Recommend whether each principle should be appended, revised into an existing section, added as a new section, or left at the skill level.
6. Draft concise rule text, but never treat rule changes as automatic; they should still be reviewed before being applied.
7. Call out the risk of over-promoting narrow or project-specific details into universal rules.

## Output expectation

- sources reviewed
- candidate principles
- append / revise / new section / keep at skill level recommendation
- target rule file or section
- concise draft text
- risks of over-generalization

## References

- `../../references/checklists/rule-distillation-checklist.md`
- `../../references/playbooks/rules-distillation-playbook.md`
- `../../references/checklists/learning-promotion-checklist.md`
