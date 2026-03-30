---
name: bosskuai-skill-stocktake
description: Use this to audit local skills, commands, and nearby guidance for overlap, staleness, weak triggers, and missing maintenance improvements.
---

# BosskuAI Skill Stocktake

Use this skill when the workspace has accumulated enough skills or commands that quality drift, overlap, or stale guidance is becoming a risk.

## How this differs from nearby skills

- **`bosskuai-rules-distill`**: extracts cross-cutting principles and proposes rule updates; this skill audits skill quality and identifies what to keep, improve, merge, or retire.
- **`bosskuai-workspace-assistant`**: orchestrates skill loading; this skill maintains the quality of what gets loaded.

## Mindset

- A skill that no one loads is technical debt, not an asset.
- Overlap between skills confuses routing and creates maintenance burden.
- The best skill is: specific enough to trigger correctly, actionable enough to change behavior, concise enough to fit in context.
- Retire rather than accumulate: a lean, high-quality skill set is worth more than a comprehensive but stale one.

## Audit dimensions

For each skill, evaluate:

| Dimension | Question | Scoring |
|-----------|----------|---------|
| **Trigger clarity** | Is the description specific enough to know when to load this skill? | Clear / Vague / Ambiguous |
| **Uniqueness** | Does this skill cover something no other skill covers? | Unique / Overlaps / Duplicate |
| **Actionability** | Does it give concrete steps, frameworks, or output formats? | Concrete / Generic / Vague |
| **Freshness** | Is the content accurate given current tool versions and practices? | Current / Needs review / Stale |
| **Token efficiency** | Is the skill the right length for what it delivers? | Efficient / Verbose / Thin |
| **Output format** | Does it specify what a good response looks like? | Yes / Partial / No |
| **Guardrails** | Does it tell the agent what NOT to do? | Yes / Partial / No |

## Verdict definitions

| Verdict | When to use |
|---------|------------|
| **Keep** | Passes all audit dimensions — no action needed |
| **Improve** | Passes trigger/uniqueness but has thin content, no output format, or missing guardrails |
| **Merge** | Two or more skills cover the same domain with significant overlap |
| **Update** | Content is correct but stale (outdated framework versions, deprecated tools) |
| **Retire** | The skill is never triggered, fully duplicated elsewhere, or no longer relevant |

## Workflow

1. **Inventory all skills**: List all skill files in `skills/` — name, description line, size (line count).
2. **Check against AGENTS.md**: Are all skills listed in the skill roster? Are there skills not in the roster?
3. **Run the audit dimensions** for each skill: flag any dimension that scores poorly.
4. **Identify overlaps**: Which skills cover adjacent or overlapping territory? Is the boundary clear?
5. **Assign a verdict**: Keep / Improve / Merge / Update / Retire for each skill.
6. **Prioritize maintenance actions**: Order by impact. A frequently loaded skill with weak content is higher priority than a rarely loaded skill that is merely verbose.
7. **Smallest safe maintenance actions**: Propose specific improvements — do not propose a full rewrite of the entire skill set at once.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not silently delete or merge skills — flag recommendations and let the user review.
- Do not improve a skill without reading its current content first.
- Do not rate a skill as stale based on age alone — check whether the content is still accurate.
- Do not propose merging skills that serve different user intents, even if they share vocabulary.

## Output format

```
Scope audited:
  Skills reviewed: [count]
  AGENTS.md consistency: [aligned / gaps found]

Audit results:
  [skill name] — Trigger: [Clear/Vague] — Unique: [Yes/Overlap] — Actionable: [Yes/Partial/No] — Fresh: [Yes/Needs review] — Verdict: [Keep/Improve/Merge/Update/Retire]
  ...

Overlap map:
  [skill A] ↔ [skill B] — [overlap description] — [recommendation: merge or clarify boundary]

Priority maintenance actions:
  1. [action] — [skill] — [reason]
  2. ...

Won't action now:
  [skill] — [why deferred]
```

## References

- `../../references/checklists/skill-health-checklist.md`
- `../../references/playbooks/skill-stocktake-playbook.md`
- `../../references/checklists/learning-promotion-checklist.md`
