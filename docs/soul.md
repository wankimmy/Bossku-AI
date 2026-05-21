# Soul System

The soul is a markdown file that defines the AI's behavioral guidelines, tone, values, and decision-making principles. It is the one document that influences every single run, regardless of which skill is selected.

## What soul.md Is

`soul.md` is a top-level guidance document stored in the `souls` table (and optionally as a file in your repo). It answers the question: "Regardless of what we are building, how should this AI behave?"

A well-written soul.md covers:

- **Communication style** — how terse or verbose, how much to explain vs. how much to just do
- **Risk posture** — how cautious to be about irreversible changes, how to flag uncertainty
- **Decision principles** — when to ask for clarification vs. proceed, how to handle ambiguity
- **Technical values** — preferred patterns (e.g. "prefer explicit error handling over try-catch swallowing"), code style defaults, naming conventions
- **Honesty norms** — when to say "I don't know", when to refuse, how to present disagreement
- **Scope discipline** — rules about blast radius, staying in scope, not touching things that weren't asked about

The soul is injected into the system prompt of every model call, before skill content and before memory. It is the highest-priority context.

## Why It Matters

Skills tell the AI *how* to do specific tasks. The soul tells it *how to be* while doing any task. Without a soul, the AI's behavior is consistent only within a skill's guidance — cross-skill consistency in tone, risk posture, and communication comes entirely from the soul.

A poorly written soul (vague, contradictory, or absent) produces an AI that behaves differently depending on which skill was matched — technically competent but unpredictably toned.

## Soul Versions

Every time soul.md is edited and saved, a new version is created. Version history is stored in the `soul_versions` table with:

- `version_number` — incrementing integer
- `content` — the full soul.md text at that version
- `change_summary` — a short description of what changed (filled by the editor)
- `created_at` — timestamp
- `created_by` — user who made the change
- `active` — only one version is active at a time

The active version is what gets injected into runs. You can view version history and diff any two versions on the `/settings/soul` page.

## How Suggestions Are Generated

`SoulSuggestionService` monitors run outcomes and generates suggestions for soul improvements. Triggers:

- Multiple runs where the audit flagged the same tone/style issue
- User feedback indicating the AI was too cautious or not cautious enough
- Brain Health detecting that runs are consistently going off-scope

Suggestions appear as diffs on the `/settings/soul` page — they show exactly which line or section to change and why. They are **never auto-applied**. The soul is too foundational to change without human judgment.

## Never Auto-Applied

This is a hard constraint. The soul shapes the AI's behavior on every run. Auto-applying a suggested soul change based on a pattern (e.g. "several users said the AI is too verbose") could produce unpredictable downstream effects across all skill domains. Instead:

1. Suggestions are presented as diffs with the reasoning
2. A human reviews, edits if needed, and confirms
3. The new version is saved and activated
4. Subsequent runs use the new version
5. You can monitor whether run outcomes improve

If a soul change makes things worse, you can revert to any previous version instantly from the version history page.

## How to Edit the Soul

1. Navigate to `/settings/soul`
2. Click **Edit Soul**
3. Make changes in the markdown editor (live preview available)
4. Add a change summary describing what you changed and why
5. Click **Save as New Version** — the previous version is automatically archived
6. The new version is immediately active for new runs (in-flight runs are not affected)

If you prefer to maintain `soul.md` as a file in your repo (for version control with git), set `SOUL_SOURCE=file` in your `.env`. The system will read from `soul.md` at the repo root and treat git history as the version history. Changes made in the UI will write back to the file.

## Example Soul Excerpt

```markdown
## Risk Posture
Always prefer reversible actions over irreversible ones. If a step cannot be undone (database drop,
production deploy, secret rotation), flag it explicitly before executing, even if the user asked for
it directly.

## Scope Discipline
Never touch files outside the scope of the user's request without asking. If fixing a bug in module A
reveals a related issue in module B, note the issue in the final review but do not fix it unless asked.

## Uncertainty
When you do not know something with high confidence, say so. "I believe X" is acceptable. "X is true"
when you are not certain is not.
```
