# Growth Experiment Checklist

## Pre-work
- [ ] Hypothesis stated: "If [change], then [metric] will [direction] by [magnitude] because [reason]"
- [ ] Primary metric defined (one metric per experiment)
- [ ] Guardrail metrics defined (what we must not harm)
- [ ] Minimum detectable effect (MDE) set: what change is worth acting on?
- [ ] Sample size calculated for 80% power at 95% confidence
- [ ] Experiment duration calculated (avoid peeking)

## Design
- [ ] Control group defined (no change / current experience)
- [ ] Variant(s) defined (one change per experiment)
- [ ] Randomization unit defined (user / session / account)
- [ ] Exclusion criteria defined
- [ ] Pre-commit decision criteria written: "We will ship if [metric] improves by [X]%"

## During experiment
- [ ] Instrumentation verified before launch (events firing correctly)
- [ ] No changes to experiment mid-flight
- [ ] Sample ratio mismatch (SRM) check at 20% through
- [ ] Novelty effect risk noted if applicable

## Post-experiment
- [ ] Statistical significance reached before reading results
- [ ] Primary metric result recorded
- [ ] Guardrail metrics checked (no regressions)
- [ ] Segment breakdown reviewed
- [ ] Learnings documented (even null results)

## Quality gate
- [ ] Decision made per pre-commit criteria (no HARKing)
- [ ] Learning logged in memory/learning-log.md
- [ ] If shipping: rollout plan with monitoring period defined
