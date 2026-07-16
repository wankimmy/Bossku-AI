# Customer Discovery Playbook

## When to use
- Before building a new product or feature
- After observing unexpected churn or low engagement
- Before repositioning or entering a new segment
- When assumptions about customer behavior haven't been validated
- Before a fundraise when you need customer evidence

## Prerequisites
- Hypothesis about the target customer (job title, company type, or context)
- 5–8 interview slots booked (minimum viable signal per segment)
- Interview guide drafted (Phase 2 below)
- Recording consent confirmed or note-taking plan in place

## Phase 1: Hypothesis Setup

**Before interviews, write down:**
1. **Target persona:** Who are you interviewing? (role, company type, context)
2. **Assumed problem:** What do you believe they struggle with?
3. **Assumed behavior:** What do you believe they do today to cope?
4. **Assumed motivation:** What outcome do they care most about?
5. **Falsification criteria:** What would change your mind?

**Why this matters:** Without explicit hypotheses, interviews confirm whatever you want to find. Writing them down forces honest comparison against findings.

## Phase 2: Interview Guide

**Structure (45–60 minutes):**

```markdown
## Interview Guide: <Persona> | <Date>

### Warm-up (5 min)
- "Tell me about your role and what a typical week looks like."
- "How long have you been in this role / at this company?"
- "What are the top 2–3 problems you're trying to solve right now?"

### Problem Exploration (15–20 min)
- "Walk me through the last time you dealt with [assumed problem]."
- "How often does this come up?"
- "What do you do when it happens today?"
- "What's the most frustrating part of that process?"
- "How much time / money does this cost you roughly?"

### Behavior and Workarounds (10–15 min)
- "Have you looked for tools or solutions? What did you find?"
- "What have you tried that didn't work?"
- "If you had a magic wand, what would the ideal outcome look like?"

### Motivation and Prioritization (10 min)
- "Of all the problems you mentioned, which one keeps you up at night?"
- "If this problem were solved, what would that mean for you / your team / your company?"
- "Is this something you or your company would pay to solve? What have you paid for similar things?"

### Close (5 min)
- "Is there anything I didn't ask that you think is important?"
- "Can you refer me to 2–3 other people who face similar challenges?"
```

**Interview rules:**
- Listen 80%, talk 20%
- Never pitch during discovery — the moment you pitch, the interview becomes a sales call and the data is corrupted
- Ask about the past ("last time") not the hypothetical ("would you...")
- Follow curiosity: if something surprising comes up, go deeper
- Silence is valuable — don't rush to fill it

## Phase 3: Data Collection

**For each interview, capture:**
- Date, interviewee role, company type, company size
- Top 3 problems they mentioned (verbatim quotes preferred)
- Current workarounds / tools they use
- Emotional intensity indicators (strong language, frustration, excitement)
- Surprising or disconfirming statements
- Referrals to follow up with

**Log format:**
```markdown
## Interview #<N>: <Role> at <Company Type> | <Date>

**Top Problems (verbatim):**
1. "..."
2. "..."

**Current Workaround:** <what they do today>
**Emotional Signal:** High / Medium / Low frustration | on: <topic>
**Surprising / Disconfirming:** <anything that challenged your hypothesis>
**Quote Worth Keeping:** "..."
**Referrals:** <name/role if any>
```

## Phase 4: Synthesis

**After 5+ interviews, look for:**

1. **Recurring themes:** Problems mentioned by ≥ 60% of interviewees
2. **Emotional weight:** Which problems triggered the strongest reactions
3. **Workaround patterns:** How people cope today (this is your indirect competition)
4. **Motivation clusters:** What outcomes they care about most (efficiency, status, revenue, risk reduction)
5. **Segment differences:** Do large vs. small companies, or different roles, have different problems?

**JTBD tagging (Jobs to Be Done):**
For each recurring problem, write a JTBD statement:
> "When I [situation], I want to [motivation], so I can [expected outcome]."

Example:
> "When I onboard a new hire, I want to track their progress without micromanaging, so I can focus on my own work and trust they'll ramp quickly."

**Persona card format:**
```markdown
## Persona: <Name (fictional)>

- **Role:** <job title>
- **Company type:** <size / industry>
- **Primary JTBD:** <core job they're trying to do>
- **Top frustrations:** 1. ... 2. ... 3. ...
- **Current tools:** <what they use today>
- **Decision criteria:** <what they would evaluate in a solution>
- **Quote:** "..."
- **Segments seen:** <how many interviewees fit this pattern>
```

## Phase 5: Output — Customer Discovery Report

```markdown
## Customer Discovery Report: <Persona> | <Date>

### Summary (3 sentences)
<What problem is most real, for whom, and what surprised you>

### Confirmed Hypotheses
- <Hypothesis> — Evidence: <source count + verbatim quote>

### Disconfirmed Hypotheses
- <Hypothesis> — What we found instead: <summary>

### Top Jobs to Be Done
1. "When I..., I want to..., so I can..." — Seen in X/Y interviews
2. ...

### Key Insights by Theme
#### Theme 1: <Name>
<Summary of pattern, supporting quotes>

#### Theme 2: <Name>
<Summary of pattern, supporting quotes>

### Persona Cards
[personas]

### Implications for Product / Positioning
1. <Specific implication>
2. ...

### Open Questions (need more interviews)
- <Question not yet answered>

### Next Steps
- [ ] <Follow-up actions>
```

## Phase 6: Evidence Threshold

**Minimum signal to proceed with building:**
- ≥ 5 interviews per target segment
- ≥ 3 interviewees independently described the same problem without prompting
- At least 1 interviewee expressed willingness to pay (even rough estimate)
- At least 1 disconfirming finding documented (shows honest search)

**Signals that suggest more discovery needed:**
- Interviewees describe different problems with no clear pattern
- Problem is acknowledged but priority is low ("nice to have")
- Workarounds are adequate and low-switching-cost
- Strong variation by company size or role — may need to narrow the segment

## Common Pitfalls

**Pitching during discovery** — the moment you describe your solution, interviewees become polite and tell you what you want to hear. Keep solution out of early interviews entirely.

**Asking hypothetical questions** — "Would you use a tool that..." gets socially desirable answers. Ask about past behavior: "Tell me about the last time..."

**Interviewing too few people** — 3 interviews is anecdote. 8–12 per segment is pattern. Don't draw conclusions from n < 5.

**Cherry-picking confirming quotes** — if you only document what supports your hypothesis, discovery is theater. Write down every disconfirming statement.

**Mixing segments** — founder and VP interviews for the same tool will produce incompatible data. Keep segments separate and synthesize within each.

**Skipping the referral ask** — the best next interviewee is someone your current interviewee recommends. Always ask for 2–3 names.
