# Growth Experiment Playbook

## When to use
- Testing a new acquisition channel before committing budget
- Optimizing a conversion funnel step (landing page, onboarding, pricing)
- Validating a product change's impact on retention or revenue
- Running an A/B test on messaging, positioning, or CTA
- Designing experiments for a growth review with team or investors

## Prerequisites
- Baseline metric available (the thing you're trying to move)
- Traffic or audience volume sufficient for statistical power (see Phase 2)
- Hypothesis written down before any test starts
- Success criteria defined before any test starts

## Phase 1: Hypothesis Formation

**The hypothesis template:**
> We believe that **[change]** will cause **[target metric]** to **[increase/decrease]** by **[magnitude]** because **[reasoning]**.
> We will know this is true when **[measurable outcome]** within **[time period]**.

**Example:**
> We believe that changing the CTA from "Start Free Trial" to "See Your First Report in 5 Minutes" will cause trial-start conversion to increase by ≥15% because specificity reduces anxiety about what "trial" involves. We will know this is true when the variant outperforms control in 2 weeks with p < 0.05.

**Hypothesis quality checks:**
- [ ] Change is specific and implementable
- [ ] Target metric is a leading indicator of revenue, not just a vanity metric
- [ ] Expected magnitude is justified (not wishful)
- [ ] Reasoning names a specific psychological or behavioral mechanism
- [ ] Success threshold is defined before the test runs

## Phase 2: Sample Size and Power Calculation

**Minimum Detectable Effect (MDE):** The smallest change worth caring about.
- If a 5% lift doesn't move the needle, set MDE = 10% or higher
- Setting MDE too low requires enormous sample sizes and long timelines

**Sample size formula (simplified):**
```
N per variant ≈ 16 × σ² / δ²

Where:
σ² = variance of the metric (for rates: p × (1-p))
δ = minimum detectable effect (absolute)
```

**Practical table for conversion rate tests:**
| Baseline Rate | MDE | N per variant | Days at 1000 visitors/day |
|---------------|-----|---------------|---------------------------|
| 5% | 1pp | ~7,300 | 7 |
| 5% | 0.5pp | ~29,000 | 29 |
| 20% | 4pp | ~1,900 | 2 |
| 20% | 2pp | ~7,700 | 8 |

**Rules:**
- Do not run with < 100 conversions per variant — results are noise
- Minimum test duration: 7 days (captures weekly cycles)
- Maximum test duration: 28 days per phase (beyond this, novelty effects dissipate)
- Use two-tailed test unless you have strong directional prior

## Phase 3: Experiment Design

**For each experiment, document:**

```markdown
## Experiment: <Name> | <Date>

### Hypothesis
<From Phase 1 template>

### Metric
- **Primary:** <conversion rate / retention D30 / CAC / etc.>
- **Guardrail metrics:** <metrics that must NOT drop — e.g., revenue per user>
- **Secondary (informational):** <metrics you'll observe but won't use to decide>

### Variants
- **Control:** <current state>
- **Variant A:** <what changes>
- (Optional) **Variant B:** <second change — avoid if possible>

### Sample Size
- Required per variant: <N>
- Current daily volume: <X>
- Estimated runtime: <N days>
- Confidence level: 95% | Power: 80%

### Traffic Split
- Control: 50% | Variant: 50%
(Or appropriate split if risk asymmetric)

### Launch Date / End Date
- Start: <date>
- Minimum end: <date + 7 days>
- Maximum end: <date + 28 days>

### Decision Criteria (pre-commit)
- Ship variant if: p < 0.05 AND primary metric up ≥ MDE AND guardrail metrics unaffected
- Keep control if: p ≥ 0.05 OR guardrail metric drops > 5%
- Extend test if: insufficient sample by end date (one extension allowed)
```

## Phase 4: Running the Experiment

**Pre-launch checklist:**
- [ ] Tracking instrumentation verified (events fire correctly in both variants)
- [ ] Sample Ratio Mismatch (SRM) check scheduled (Day 1 and Day 3)
- [ ] No other tests running on the same users
- [ ] Guardrail metrics set up in dashboard
- [ ] No announcements or other changes planned mid-test

**During the test:**
- Check SRM after Day 1 and Day 3 — if traffic split differs from intended by > 5%, stop and investigate
- Do NOT peek at primary metric results mid-test and adjust
- Log any external events that could confound (product outages, news, seasonal spikes)
- Do NOT stop early because results look good — this inflates false positive rate

**SRM check:**
```
Expected control visitors = total × split %
Expected variant visitors = total × (1 - split %)
Chi-square test on actual vs. expected — if p < 0.05, SRM exists → invalidate
```

## Phase 5: Analysis and Decision

**Post-test analysis steps:**
1. Confirm sample size was reached
2. Run SRM check — invalidate if failed
3. Calculate p-value (two-tailed t-test or chi-square for rates)
4. Calculate relative lift and confidence interval
5. Check guardrail metrics
6. Check secondary metrics for unexpected effects

**Report format:**
```markdown
## Experiment Results: <Name> | <Date>

### Summary
Verdict: SHIP / KEEP CONTROL / INCONCLUSIVE / INVALIDATED

### Metrics
| Metric | Control | Variant | Lift | p-value | Significant? |
|--------|---------|---------|------|---------|--------------|
| Primary | X% | X% | +X% | 0.0X | Yes/No |
| Guardrail | X% | X% | +X% | 0.XX | Yes/No |

### Sample
- Control: N=X | Variant: N=X
- SRM check: PASS / FAIL
- Test duration: X days

### Interpretation
<What the results mean in plain language>

### Decision
<Ship / Keep control / iterate + why>

### Learning
<What this tells us about user behavior — even if result was null>
```

**Decision rules for inconclusive results:**
- Null result is still learning — document what didn't move the metric
- If direction was positive but underpowered: extend once (one extension maximum)
- If variant underperformed: kill it, don't extend

## Phase 6: Iteration and Learning Log

**After each experiment, log to the growth learning log:**
```markdown
| Date | Experiment | Hypothesis | Result | Key Learning |
|------|------------|------------|--------|--------------|
| ... | CTA copy test | Specificity ↑ conversion | +18%, p=0.02 ✓ | Specific outcome > generic action |
| ... | Pricing page reorder | Social proof ↑ conversion | null, p=0.44 | Pricing page is not the bottleneck |
```

**Build an experiment backlog — prioritize by:**
- **ICE score:** Impact (1–5) × Confidence (1–5) × Ease (1–5)
- Run highest ICE first
- Never run more than 2 experiments on the same funnel step simultaneously

## Common Pitfalls

**Peeking at results and stopping early** — stopping when p hits 0.05 (before planned end date) inflates false positive rate to 20–30%. Commit to the plan.

**Testing too many variables at once** — multivariate tests require dramatically larger samples and make causation unclear. Change one thing at a time.

**Ignoring SRM** — a sample ratio mismatch means your randomization is broken and results are meaningless. Always check.

**Vanity metric as primary** — testing clicks when you care about revenue leads to winning tests that don't matter. Primary metric must connect to business value.

**No pre-committed decision criteria** — deciding how to interpret results after seeing them introduces bias. Always pre-commit the decision rule.

**Small sample confidence** — with < 100 conversions per variant, even a "significant" result is usually noise. Enforce minimum sample size.

**Over-testing** — more experiments is not always better. Each test has a cost (eng time, user exposure, opportunity cost). Run fewer, higher-quality experiments.
