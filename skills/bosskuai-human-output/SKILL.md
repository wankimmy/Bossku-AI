---
name: bosskuai-human-output
description: Use this for rewriting copy, docs, README text, landing pages, posts, emails, UI microcopy, and responses so they sound specific, practical, and human instead of generic AI output.
---

# BosskuAI Human Output

Use this skill when the user asks to humanize, simplify, rewrite, remove AI slop, make copy sound less generated, or improve public-facing text.

## Goal

Make the output sound like a practical person wrote it for a real context.
Do not make it more polished by default. Make it more specific, more grounded, and less template-like.

## Remove

- Empty hype: `unlock`, `elevate`, `seamless`, `revolutionary`, `game-changing`, `cutting-edge`.
- Filler setup: `in today's fast-paced world`, `whether you're`, `designed to help you`.
- Formula lines: `not just X, but Y`, three neat benefits with no proof, fake contrast pairs.
- Vague confidence: `powerful`, `robust`, `comprehensive`, `effortless`, unless evidence is shown.
- Over-clean rhythm: same sentence length, same bullet shape, repeated section cadence.
- Decorative punctuation used as style instead of clarity.

## Prefer

- Plain claims with proof, example, or limitation.
- Specific nouns over broad adjectives.
- Short sentences mixed with medium ones.
- Real tradeoffs: what works, what still needs review, what can fail.
- Product-specific wording from the repo, screenshot, user notes, or real workflow.
- Malaysian/indie-builder voice only when the project context actually fits.

## Voice Calibration

When source text exists, preserve the user's intent and domain terms. Fix the texture, not the meaning.

Check:

1. Does this sound like any AI SaaS landing page?
2. Are claims unsupported?
3. Is every sentence too balanced or too neat?
4. Can one concrete example replace one generic phrase?
5. Would a real user say this line?

If yes, rewrite once before final output.

## Output Rules

- Keep the rewritten text first.
- Add a short note only if a tradeoff or assumption matters.
- Do not explain every edit unless asked.
- Do not over-localize. Use Malay/English mix only when requested or context clearly points there.

## References

- `../../references/checklists/anti-ai-writing-checklist.md`
