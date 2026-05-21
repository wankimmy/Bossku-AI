# Auto Skill Generation

BosskuAI can generate new skill candidates automatically by observing patterns across completed runs. This page explains how the pipeline works, what triggers candidate creation, and how the approval workflow protects against bad skills being silently activated.

## Overview

```
Runs complete
  └─► LearningEngine extracts patterns
        └─► Pattern occurrence_count >= 3
              └─► SkillCandidateGenerator drafts SKILL.md
                    └─► Stored as skill_candidate (pending_review)
                          └─► Visible on /brain Skill Candidates tab
                                └─► Admin approves or rejects
                                      └─► On approval: SkillIndexService activates skill
```

No skill is ever auto-activated. Every generated candidate requires explicit human approval.

## Pattern Detection

`LearningEngine` runs as a queued job after every successful run. It:

1. **Normalizes the intent** — strips run-specific details (file names, variable names) from the prompt to produce a stable `intent_fingerprint`
2. **Records approach** — summarizes what the executor did (which tool types were invoked, in what order)
3. **Upserts the pattern** — increments `occurrence_count` if a matching fingerprint exists, or creates a new pattern record

Two patterns are considered the same if their intent fingerprint embeddings have cosine similarity above `0.85`. This prevents trivially different phrasings from creating duplicate patterns.

## The ≥3 Occurrence Threshold

The threshold is deliberately conservative. One run proving an approach works could be luck. Two runs might be the same user repeating themselves. Three runs from the same pattern is meaningful signal that:

- Multiple distinct users (or distinct sessions) encountered the same need
- The approach was consistently successful (runs must have `status = completed` and `audit_quality >= 0.6` to count toward the threshold)
- A skill would provide genuine value on the next occurrence

The threshold is configurable in `config/bossku.php` as `skill_generation.min_occurrences`. Setting it below 3 is not recommended — it produces noisy candidates.

## Draft SKILL.md Generation

When a pattern crosses the threshold, `SkillCandidateGenerator` produces a complete draft SKILL.md by:

1. Calling the configured planning model with:
   - The pattern's intent fingerprint as the task description
   - The full transcripts of the top 3 contributing runs as examples
   - The current skill library content (to check for duplication)
   - The active `soul.md` for tone guidance
2. Parsing the model output into the required skill sections
3. Validating that all required sections are present (Description, Trigger Keywords, Approach, Anti-Patterns)
4. Scoring the draft against the quality rubric — drafts scoring below `0.5` are not surfaced until the quality is improved

The draft is stored in the `skill_candidates` table with:
- `status: pending_review`
- `source: auto_generated`
- `pattern_id` — the pattern that triggered generation
- `run_ids` — the contributing run IDs (for traceability)
- `draft_content` — the generated SKILL.md text
- `generated_at` — timestamp

## Approval Workflow

On the `/brain` page, the **Skill Candidates** tab lists all pending candidates with:

- The draft skill name and description
- A diff-style view showing the generated content
- Links to the contributing runs
- The pattern occurrence count and average quality score
- A `requires_manual_review` flag if the skill falls into a risky category

**To approve**: click Approve, optionally edit the content inline first. The skill enters the active index immediately after approval.

**To reject**: click Reject with a reason. The pattern is marked `rejected` and will not re-surface unless occurrence count continues to grow past a higher threshold (default: `occurrence_count >= 10` re-triggers candidate generation with a note that a previous draft was rejected).

**To edit before approving**: the candidate content is fully editable in the approval UI. Edit the trigger keywords, tighten the approach steps, then approve. The final saved version is what gets activated — the original draft is preserved in the `draft_content` field for audit purposes.

## Risky Categories and Mandatory Manual Review

The following skill categories are always assigned `requires_manual_review = true`, regardless of quality score or occurrence count:

| Category | Why |
|---|---|
| `payment-gateway` | Incorrect guidance can cause financial loss or compliance violations |
| `security` | Wrong patterns can introduce vulnerabilities that are hard to detect |
| `deployment` | Bad steps can cause production outages |
| `auth` | Flawed auth patterns create exploitable attack surfaces |

For these categories, the approval button on `/brain` is replaced with a **Request Review** button that notifies configured admin users and requires an explicit admin-role action. Non-admin users can review the content but cannot approve.

Category classification is performed by `RiskClassifier` during candidate generation, using the same rule engine used for run-time governance.

## Keeping the Skill Library Healthy

Auto-generated skills should be periodically reviewed:

- Skills with `quality_score < 0.5` after 20 runs are flagged for re-review
- Skills whose trigger keywords overlap heavily with existing skills are flagged as potential duplicates
- The `/brain` Brain Health tab tracks the ratio of auto-generated to human-written skills and warns if auto-generated skills have lower average quality scores
