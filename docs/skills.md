# Skill System

Skills are the primary mechanism by which BosskuAI applies domain expertise. A skill is a markdown file that tells the orchestrator how to approach a specific category of task. When a run matches a skill, its content is injected into the model's system prompt before planning and execution begin.

## What a SKILL.md Contains

Every skill file follows this structure:

```markdown
# Skill: <name>

## Description
One paragraph explaining what this skill covers and when to use it.

## Trigger Keywords
Comma-separated terms that signal this skill is relevant (e.g. "stripe, payment, webhook, checkout").

## Approach
Step-by-step guidance for how to handle tasks in this domain.
1. ...
2. ...

## Anti-Patterns
Common mistakes to avoid in this domain.
- Do not ...
- Never ...

## Examples
Optional: 1-3 short worked examples showing good outputs.
```

The `Trigger Keywords` field drives keyword-based pre-filtering before embedding similarity is computed. Including precise terms improves match accuracy and reduces false positives.

## How Skills Are Loaded

At application boot, `SkillIndexService` scans the `skills/` directory (and the database `skills` table for user-created skills) and builds an in-memory index. Each skill entry stores:

- The full markdown content
- A pre-computed embedding vector
- The `quality_score` (0.0–1.0)
- The `version` number
- Active/inactive status

The index is rebuilt on a configurable schedule (default: every 5 minutes) and on demand via `POST /api/skills/reindex`.

## How Skills Are Matched

`SkillMatcherService` uses a two-stage process:

**Stage 1 — keyword pre-filter**: The user prompt is tokenized and matched against each skill's trigger keywords. Only skills with at least one keyword hit proceed to Stage 2. This keeps embedding costs low for large skill libraries.

**Stage 2 — semantic similarity**: The prompt is embedded and cosine similarity is computed against each candidate skill's embedding. The skill with the highest similarity score above `SKILL_MATCH_THRESHOLD` (default `0.6`) is selected.

If no skill clears the threshold, the `cofounder` skill is used as the default catch-all.

## Quality Scoring

Each skill has a `quality_score` between 0.0 and 1.0. This score is updated by `LearningEngine` after each run that used the skill:

- **Positive contribution**: high audit quality scores, positive user feedback, run completion without failures
- **Negative contribution**: audit findings at severity `high` or `critical`, user negative feedback, run failures attributed to bad skill guidance

Skills with `quality_score < 0.4` are automatically flagged as `weak` and shown with a warning badge in the skill list. The `/brain` Skill Candidates tab will suggest improvements.

## Version Tracking

Every time a skill's content is edited and saved, the version number increments and the previous version is archived. This means:

- You can see what changed between versions
- Failed runs can be traced back to the exact skill version that was active at the time
- Reverting to a previous version is supported via `POST /api/skills/{id}/revert/{version}`

## Writing a Custom Skill

1. Navigate to `/skills/new` in the UI, or create a file in `skills/<name>.md`
2. Fill in all required sections: Description, Trigger Keywords, Approach, Anti-Patterns
3. Save — the skill is immediately available for matching (the index rebuilds within 5 minutes, or trigger a manual reindex)
4. Test by submitting a run whose prompt matches your trigger keywords, then check the run detail to confirm your skill was selected

**Tips for effective skills:**

- Keep the Approach section prescriptive, not descriptive. Say "Do X before Y" not "X and Y are both important."
- Anti-Patterns should be specific to your domain. Generic advice (e.g. "write clean code") belongs in the soul, not a skill.
- Include at least 5 distinct trigger keywords to improve recall.
- After 10 runs using the skill, check the quality score. If it is below 0.6, review the Anti-Patterns section — that is usually where skill guidance goes wrong.

## Skill Lifecycle

```
Draft → Active → Weak (if quality drops) → Archived
                  └─► Improved → Active
```

Skills can also be deactivated manually without archiving, preserving their history while removing them from matching.
