# Growth Experiment

## When to use
- Designing an A/B test or multivariate experiment
- Planning a channel test to measure CAC or conversion lift
- Building a referral loop, onboarding flow, or pricing test
- User asks "should we test X vs Y" or "how do we know if this change worked"
- Establishing an experimentation framework or process for the first time
- Post-experiment analysis and deciding whether to ship, iterate, or kill

## How this differs from nearby skills
- **vs marketing-growth:** marketing-growth defines channel strategy, content plans, and growth loops at a strategic level. growth-experiment designs rigorous, statistically grounded experiments with sample sizes, stopping rules, and decision criteria before running anything.
- **vs paid-acquisition-monetization:** paid-acquisition optimizes spend and CAC/LTV ratios operationally. growth-experiment creates the measurement structure that validates whether a new channel or creative actually works.
- **vs analytics-metrics:** analytics-metrics tracks and interprets existing data. growth-experiment designs the conditions under which valid causal conclusions can be drawn.

## MCP requirements
- **Exa (optional):** Benchmark conversion rates and experiment durations from industry sources. Graceful degradation: fall back to stated industry norms if Exa unavailable.
- No other MCP servers required.

## Workflow

### 1. State the hypothesis clearly
Use the format: "If we [change], then [metric] will [direction] by [magnitude] because [reason]."
- Example: "If we reduce the onboarding flow from 7 steps to 3 steps, then day-7 activation will increase by 15% because users reach the core action faster."
- Ensure the hypothesis is falsifiable and the mechanism is articulated.

### 2. Define success metric and minimum detectable effect (MDE)
- Choose one primary metric (not a composite). Secondary metrics are tracked but do not determine outcome.
- State the MDE: the smallest improvement that would justify shipping the change. Do not set MDE after seeing data.
- Set the statistical parameters: significance level (α = 0.05 default), power (1-β = 0.80 default).

### 3. Choose experiment type
| Type | Use when |
|---|---|
| A/B test | Single variable, clear control vs treatment |
| A/B/n (multi-arm) | Multiple variants to compare simultaneously |
| Holdout test | Measuring impact of a launched feature on a held-back group |
| Multi-arm bandit | Traffic should shift dynamically to best performer |
| Pre/post (no holdout) | Experiment is impossible; document confounders explicitly |

### 4. Calculate sample size and duration
Use the formula for two-proportion z-test or t-test depending on metric type:
- n = (Z_α/2 + Z_β)² × (p(1-p) + p'(1-p')) / (p' - p)²
- State: baseline conversion rate, expected lift, daily eligible users
- Duration = required sample / daily traffic. Cap at 4 weeks; if longer, reduce scope.
- If traffic is too low for significance: explore proxy metrics, reduce variant count, or accept lower power with explicit documentation.

### 5. Design the execution plan
- Randomization unit (user ID, session, device — default: user ID for consistency)
- Allocation split (default 50/50; justify any unequal splits)
- Instrumentation: what events are tracked, how, and where they're logged
- Who owns the experiment day-to-day
- QA check: verify tracking fires correctly in staging before launch

### 6. Define decision criteria before running
Write these down before any data is seen:
- "We will ship if: primary metric improves by ≥ MDE at p < 0.05 with no significant harm to guardrail metrics."
- "We will kill if: primary metric shows significant harm OR no movement after full sample."
- "We will iterate if: directionally positive but below MDE — rerun with revised hypothesis."
- Set any early-stopping rules for harm (guardrail metric degradation threshold).

### 7. Post-experiment analysis template
After the experiment concludes:
1. Report primary metric result (lift, confidence interval, p-value)
2. Check all guardrail metrics for negative effects
3. Segment results by key cohorts (new vs returning, mobile vs desktop)
4. State the decision per criteria defined in step 6
5. Document learnings — even from failed experiments

## Output format

**Experiment Card:**
```
Hypothesis: [If/Then/Because]
Primary metric: [metric name, baseline value, MDE]
Type: [A/B / holdout / bandit]
Variants: [Control, Treatment A, ...]
Sample size: [per variant]
Duration: [X days at Y daily users]
Randomization unit: [user ID / session]
Statistical params: α=0.05, power=0.80
Decision criteria:
  Ship if: ...
  Kill if: ...
  Iterate if: ...
Early-stop guardrails: [metric, threshold]
Owner: [name]
```

**Post-experiment report:**
- Primary result (lift, CI, p-value)
- Guardrail results
- Segmentation findings
- Decision + rationale
- Learning logged to memory

## Guardrails
- Never declare statistical significance before the pre-specified sample size is reached (no peeking).
- Decision criteria must be written before the experiment starts — not after seeing data.
- Document learnings from failed experiments; they are as valuable as wins.
- Do not run more than 3 simultaneous experiments on the same user surface without interaction analysis.
- Multi-arm bandit is inappropriate when you need a clean causal read — use A/B instead.
- Never use "directionally positive" as justification to ship without reaching significance.
