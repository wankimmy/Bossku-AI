---
name: bosskuai-rules-distill
description: Use this to extract repeated cross-cutting principles from skills and references, then propose safe rule updates instead of letting important guidance stay fragmented.
---

# BosskuAI Rules Distill

Use this skill when the repo has learned useful principles across multiple skills or references and those principles should become stronger shared rules.

## How this differs from nearby skills

- **`bosskuai-skill-stocktake`**: audits skill quality and coverage; this skill extracts cross-cutting principles from skills and promotes them into shared rules.
- **`bosskuai-workspace-assistant`**: applies rules during execution; this skill improves the rules that govern that execution.

## Mindset

- Rules should change agent behavior. If a candidate rule would not change anything, it is not a rule — it is documentation.
- The cost of a bad rule is high: it applies in all future sessions, not just this one. Be conservative.
- Over-promotion is a real risk: a principle that is project-specific should stay in a skill, not become a universal rule.
- Rules should be short and unambiguous. If a rule needs a paragraph to explain, it belongs in a playbook.

## When a principle belongs at the rules level vs other levels

| Artifact | What belongs there |
|----------|--------------------|
| **Rule** (`bosskuai.md`, CLAUDE.md) | Cross-cutting behaviors that should apply in every session — model selection, safety defaults, escalation criteria |
| **Skill** (SKILL.md) | Domain-specific workflow, frameworks, checklists, output formats |
| **Playbook** | Step-by-step how-to for a repeated procedure |
| **Checklist** | Exhaustive verification list for a specific task |
| **Memory** | Durable project-specific context and learnings |

A principle is a **rule candidate** if:
- It appears in 3+ skills or references
- It applies regardless of domain (product, engineering, security, marketing)
- Not having it as a rule has caused repeated mistakes

## Promotion checklist

Before promoting a principle to a rule:
- [ ] Read the current rule files to check for near-duplicates with different wording
- [ ] State the exact behavior change the rule would produce
- [ ] Confirm the principle appears in at least 3 independent contexts
- [ ] Verify the principle is truly universal, not project-specific
- [ ] Draft the rule text in ≤ 2 sentences
- [ ] Identify the risk of over-generalization: in what context could this rule give bad guidance?

## Workflow

1. **Read the relevant skills and references** before proposing any rule changes. Do not work from memory.
2. **Identify candidate principles**: Look for cross-cutting guidance that appears in multiple skills.
3. **Filter for true universality**: Remove anything that is domain-specific, project-specific, or stack-specific.
4. **Check existing rules**: Compare candidates against `bosskuai.md` and CLAUDE.md line by line — avoid adding duplicates with different wording.
5. **Assign a disposition** for each candidate:
   - **Append**: new principle, no existing rule covers it
   - **Revise existing**: principle is covered but the current rule is weak or ambiguous
   - **New section**: multiple related candidates that form a coherent new rule section
   - **Keep at skill level**: valid principle but too narrow or domain-specific for a universal rule
6. **Draft rule text**: Concise, unambiguous, ≤ 2 sentences per rule.
7. **Flag over-generalization risk**: For each proposed rule, state the edge case where it could give bad guidance.
8. **Never auto-apply**: Rule changes should always be reviewed by the user before being applied.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not promote a principle to a rule without checking the existing rules for near-duplicates.
- Do not promote project-specific lessons as universal rules.
- Do not propose rule changes that are not grounded in observed repeated behavior.
- Do not auto-apply rule changes — always surface for review.
- Do not write vague rules. "Be careful with security" is not a rule.

## Output format

```
Sources reviewed:
  [skill / reference / checklist names]

Candidate principles:
  [principle] — Source(s): [where it appears] — Universal? [yes / no]

Rule proposals:
  [principle] — Disposition: [Append / Revise / New section / Keep at skill level]
  Target rule file / section: [file and section]
  Draft text: "[proposed rule text — ≤ 2 sentences]"
  Over-generalization risk: [edge case where this rule could give bad guidance]

Won't promote (and why):
  [principle] — [reason: too narrow / already covered / project-specific]
```

## References

- `../../references/checklists/rule-distillation-checklist.md`
- `../../references/playbooks/rules-distillation-playbook.md`
- `../../references/checklists/learning-promotion-checklist.md`
