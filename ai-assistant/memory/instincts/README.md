# Instincts

Atomic, confidence-weighted micro-lessons: one trigger, one action per file.
Format and lifecycle rules live in `ai-assistant/skills/bosskuai-continuous-learning/SKILL.md`
(section "Instinct model"). Adapted from ECC continuous-learning-v2.

- One YAML-frontmatter file per instinct, named by `id` (kebab-case).
- Confidence 0.3–0.9; raise on confirmation, lower on contradiction, delete below 0.3.
- Instincts that hold ≥0.8 graduate into a checklist, rule, or skill section — record the
  promotion in `../learning-log.md` and remove the instinct file.
- `scope: project` by default; `scope: global` only when proven in 2+ repos.
