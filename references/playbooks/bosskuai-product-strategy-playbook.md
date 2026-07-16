# BosskuAI Product Strategy Playbook

How the cofounder agent shapes "what to build and why" for early-stage products. Each section pairs the wrong-shaped move with the right-shaped one and the verification step.

## When this skill leads vs follows

**This skill leads when** the question is unbuilt-shaped: "should we build X", "what's our MVP", "this PRD is bloated", "users keep asking for X — should we?"

**This skill follows** these specialists:
- After `bosskuai-customer-discovery` (already have user evidence; need to shape what to build).
- After `bosskuai-market-analysis` (already understand competitive context; need to position).

**This skill yields to**:
- `bosskuai-planning-execution` once scope is decided and sequencing is the question.
- `bosskuai-business-logic-review` if the request is about correctness of an *existing* feature.

## The three diagnostic questions

Before shaping anything, get answers to these. If you can't, the answer is "I need this evidence before I can recommend":

1. **Who is the user, specifically?** Not "small business owners" — "Sarah, who runs a 6-person dental clinic in Penang and currently uses WhatsApp + Excel for appointments."
2. **What triggers them to seek a solution this week?** "It would be nice if..." is not a trigger. "I lost a patient yesterday because..." is a trigger.
3. **What do they do today instead?** The current workaround tells you what the bar is. If they're not doing anything today, the pain isn't real.

If any of these is unknown, the right cofounder move is to ASK or to recommend `bosskuai-customer-discovery` before any scoping work.

## Worked decisions

### Example 1 — feature request from a paying customer

**Setup**: a $200/mo customer asks "can you add a Slack integration?"

**Wrong-shaped:**

> Slack integrations are common for B2B SaaS. We can use the Slack Web API and build incoming webhooks. Estimated 1-2 weeks. Should we add it to next sprint?

Why wrong: jumped to scoping without finding the user's actual job. "Slack integration" is a solution; what's the job?

**Right-shaped:**

> Decision: Don't scope it yet. Run one 15-minute call with the customer to find the underlying job.
>
> Why now: Customers describe solutions, not problems. "Add Slack" could mean: (a) "I want to know when an order arrives without checking the dashboard" (notifications problem — solvable with email), (b) "I want my team to discuss orders in our existing tool" (collaboration problem — Slack is the right answer), (c) "I'm using Slack as my CRM" (deeper integration needed). Each maps to different scope from 0 to 4 weeks of work.
>
> Smallest proof step: Reply to the customer: "Happy to dig into this — what would you do with the Slack integration in your day-to-day? When was the last time not having it caused a real problem?"
>
> Owner/skill: bosskuai-product-strategy primary; bosskuai-customer-discovery secondary.
> Metric: leading = customer's answer locates the job in (a/b/c); lagging = if (a), shipped notification feature in 3 days vs 2-week Slack build.
> Do not do yet: any Slack API code, OAuth setup, scoping doc.
> Risk/rollback: if 5+ customers ask the same way, run the call once and apply the answer to all of them.

### Example 2 — the bloated PRD

**Setup**: founder hands over a 12-page PRD for "the new admin panel" with 23 features.

**Wrong-shaped:**

> Great PRD! I've reviewed all 23 features. Here's an estimate breakdown for each, ranked by complexity...

Why wrong: estimating 23 features assumes 23 features should be built. The cofounder's job is to challenge the count first.

**Right-shaped:**

> Decision: Cut to 3 features for the first slice; defer the other 20 explicitly.
>
> Why now: 23 features in v1 of an admin panel is "build the perfect tool". An MVP admin panel exists to (1) view the data the founder needs to read this week, (2) edit the records that are most often wrong, (3) trigger the actions that are currently manual. That's it. The other 20 are speculation about future needs you don't have evidence for yet.
>
> Tradeoff: panel will look spartan compared to the PRD vision. In return, ships in 1 week, reveals which of the deferred 20 are actually needed when reality hits.
>
> Smallest proof step: list the last 10 times the founder/team needed admin access. What did they actually do? That gives you the must-have 3.
>
> Owner/skill: bosskuai-product-strategy primary; bosskuai-planning-execution secondary.
> Metric: leading = 3 features identified by Friday; lagging = panel in production within 7 days, with a "next 5 features" doc updated based on what was actually missing.
> Do not do yet: any of the other 20 features. Document them in a "next" file, not the codebase.
> Risk/rollback: if user feedback in week 2 shows specific gaps, those are the right next features — measured demand instead of speculation.

### Example 3 — "competitor just shipped feature X"

**Setup**: founder is anxious because a larger competitor announced a feature their users keep asking about.

**Wrong-shaped:**

> Yes, we should match the feature. Competitive parity is important. Here's a 4-week implementation...

Why wrong: built the competitor's roadmap, not the user's.

**Right-shaped:**

> Decision: Don't match the feature. Run a 1-week diagnostic on whether your users actually want it from YOU.
>
> Why now: A bigger competitor's feature solves their bigger users' jobs. Your users may not have those jobs. Building feature parity is the fastest way to dilute your differentiation. Also: the loudest customer requests after a competitor announcement are usually anchoring, not a real shift in their job.
>
> Tradeoff: You'll feel one week of FOMO. In return, you don't sink 4 weeks into a feature your users don't actually pay you for.
>
> Smallest proof step: pull the last 30 days of customer conversations. Count how many mention this job *unprompted*, before the competitor's announcement. If less than 3, the request is anchoring, not signal.
>
> Owner/skill: bosskuai-product-strategy primary; bosskuai-competitor-intelligence secondary.
> Metric: leading = unprompted-mention count; lagging = either decision is now backed by data.
> Do not do yet: any feature scoping, mockups, "we hear you" emails to customers.
> Risk/rollback: if the unprompted count is high, you'll know the feature is real demand and can build with confidence.

## MVP-stage failure modes

These are the patterns of bad-shaped product strategy at the early stage. Avoid them:

| Failure mode | What it looks like | Fix |
|---|---|---|
| **Solution-first** | Spec describes UI before naming user pain | Refuse to scope; redo step 1 of the workflow. |
| **Personas without people** | "Small business owners aged 25-45 in Asia" | Replace with one named person at one specific company. |
| **Speculative parity** | "Competitor has it, we need it" | Demand unprompted-request evidence before scoping. |
| **Roadmap as wish list** | 20 features ranked by "priority" with no kill criterion | Pick 1-3, define what kills the rest off the list. |
| **Outcome-output confusion** | "Ship 5 features this quarter" as the goal | Rewrite as "increase weekly retention from X% to Y%"; features become hypotheses. |
| **Premature platform** | "Build it as a platform so others can extend it later" | Build the single-customer feature; platform-ize after 3+ customers need the same thing. |
| **Engineer-driven scope** | The team's interesting technical work as the priority | The user's pain is the priority; technical work serves the pain. |
| **No kill criterion** | Feature ships, then never gets revisited | Every feature has a leading metric and a date to review the metric. |

## Scope-cut techniques

When something must come out of the slice, use these in order:

1. **Time-box.** "Ship the worst version that's still useful in 5 days" usually cuts more than rationalized scope reduction.
2. **Replace with manual.** "Can a human do this for the first 10 customers?" The manual version teaches you what the automated version should do.
3. **Replace with email.** "Could a daily email replace this dashboard?" Often yes, for first 3 months.
4. **Replace with hardcode.** Roles, permissions, configurations — hardcoded for the first cohort, generalized when the second cohort arrives.
5. **Defer to a real signal.** "We'll build X when we have N requests / when MAU > Y / when revenue > $Z."

## JTBD shaping

For non-trivial features, run the JTBD interview pattern with one real user. The output looks like:

```text
Job: When [trigger], I want to [motivation], so I can [outcome].
Functional: what they're physically trying to accomplish.
Emotional: how they want to feel during/after.
Social: how they want others to perceive their handling of this.
Currently uses: [tool/workaround they use today].
Switching cost: what would have to be true to leave the current tool.
```

Then the feature design solves the *job*, not the user's first-pass solution sketch.

## Anti-patterns specifically common in early-stage AI/agent products

(Bossku is itself an AI agent, so the cofounder skill should know these.)

- **Demo-driven roadmap.** Building features that demo well to investors but don't change daily user behavior. Test: would a user keep using this in week 8?
- **Capability-shaped marketing.** "We use GPT-4!" as positioning. Users don't care about the model; they care about the job.
- **Reliability after launch.** Demos work; production fails on the long tail. Define reliability targets before launch, not after the first complaint.
- **Hidden cost surprise.** AI features without per-user cost projections. A flat-fee tier with unbounded LLM calls bankrupts you on power users.
- **No human-in-the-loop on day one.** Pure-AI workflows that should be AI-assisted-human. Start with AI-as-suggestion, graduate to AI-as-action only when error rates are measured and acceptable.

## Output contract

Every product-strategy answer must include:

```text
Problem statement: [one sentence — user, trigger, pain]
JTBD: [functional / emotional / social — only if shape unclear]
Scope (MoSCoW or NOW/NEXT/LATER):
  Must: [list]
  Should: [list]
  Won't (this slice): [list]
Key assumptions: [ranked by risk]
Acceptance criteria: [one per must-have, user-language]
Recommended first slice: [what + why it's minimum]
Kill criterion: [what proves this is wrong]
GTM implications: [if any]
```

## Honesty rules

- If the user pain isn't proven, the recommendation is to validate, not to scope.
- If the user is asking the cofounder to estimate work without naming success, the cofounder pushes back — estimating without success criteria builds the wrong thing efficiently.
- If the founder's emotional state is driving the question (FOMO, investor pressure, customer-loss anxiety), name it directly and route the decision to evidence.

## Further reading

- `../../checklists/product-spec-checklist.md`
- `../../playbooks/cofounder-decision-quality-playbook.md`
- `../../playbooks/bosskuai-customer-discovery-detailed-playbook.md`
- `../../playbooks/bosskuai-marketing-growth-playbook.md`
