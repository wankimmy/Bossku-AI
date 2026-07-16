# Lead Intelligence Playbook

## When to use
- Building a targeted outbound list for customer, investor, or partner outreach
- Enriching an existing CRM list with intent and signal data
- Finding warm paths into accounts before cold outreach
- Setting up a repeatable lead generation workflow for a new channel
- Investor targeting for a fundraise

## Prerequisites
- Exa MCP configured (`EXA_API_KEY` set) — required for signal discovery
- Playwright MCP available — optional, for LinkedIn or directory scraping
- ICP (Ideal Customer Profile) defined before running any discovery
- CRM or tracking sheet ready to receive output

## Phase 1: ICP Definition

**Define the ICP before any search. A vague ICP produces noise.**

```markdown
## ICP Definition: <Campaign Name>

### Lead type
Customer / Investor / Partner / Hire (circle one)

### Target profile
- Industry: <e.g., B2B SaaS, fintech, healthcare>
- Company size: <e.g., 10–200 employees, Series A–B>
- Geography: <e.g., US/Canada, remote-friendly>
- Title(s): <e.g., VP Engineering, Head of Product, CTO>
- Tech stack signals: <e.g., uses Salesforce, built on AWS>
- Stage signals: <e.g., recently funded, headcount growing>

### Outreach goal
Meeting / Intro call / Demo / Partnership discussion / Investment conversation

### Channel
Email / LinkedIn / Warm intro / Event / DM

### Exclusions
- Companies already in CRM
- Direct competitors
- Companies with < $1M ARR (if known)
```

## Phase 2: Signal Discovery with Exa

**Design 3–5 search angles per ICP segment — do not just vary keywords:**

```
Angle 1 (direct fit): "<title> at <company type> in <geography>"
Angle 2 (intent signal): "<company type> hiring <role> — infers growth and pain"
Angle 3 (event signal): "<company type> raised Series A 2025"
Angle 4 (community): "founder posting about <pain point> on LinkedIn"
Angle 5 (directory): "<industry> conference speaker list OR award list"
```

**For investor targeting:**
```
Angle 1: "<stage> investor <vertical> portfolio"
Angle 2: "seed fund invested in <comparable company>"
Angle 3: "VC partner wrote about <your market or problem>"
Angle 4: "<firm> recent investments 2024 2025"
Angle 5: "angel investor <domain expertise> check size <range>"
```

**Capture per lead:**
- Name, title, company
- Company size and stage (if available)
- Source URL
- Signal type (job posting, funding, content, directory)
- Date of signal
- Any direct quotes or context from the signal

## Phase 3: Warm Path Identification

**For each lead, check:**

1. **Mutual connections:** Do any advisors, investors, or current customers know this person?
2. **Shared communities:** Same Slack group, Discord, alumni network, accelerator cohort?
3. **Content engagement:** Have they engaged with your content, your investors' content, or key voices in your space?
4. **Job history overlap:** Have they worked at a company where you have an existing relationship?
5. **Event overlap:** Same conference, panel, or online event?

**Warm path scoring:**
- **5** — Direct mutual who can make a warm intro
- **4** — Two degrees away through a strong relationship
- **3** — Shared community or alumni where you can legitimately reference it
- **2** — Engaged with shared content (weak signal but an opener)
- **1** — No warm path found — cold outreach only

## Phase 4: Lead Scoring

**Score each lead on three dimensions (1–5 each):**

| Dimension | Score | Criteria |
|-----------|-------|----------|
| **Fit** | 1–5 | ICP match: title, company size, industry, stage alignment |
| **Timing** | 1–5 | Intent signals: recent funding, hiring in relevant area, product launch |
| **Warm path** | 1–5 | From Phase 3 above |

**Priority tiers:**
- Total ≥ 12: Priority 1 — reach this week
- Total 9–11: Priority 2 — reach this month
- Total 6–8: Priority 3 — nurture sequence or next quarter
- Total < 6: Deprioritize — does not meet ICP closely enough

**Minimum bar to contact:** Fit ≥ 3. Do not contact leads who don't meet the ICP minimum even if timing or warm path is high.

## Phase 5: Outreach Preparation

**Personalization hook (required for every outreach):**

Find one specific, non-generic reason to reach out:
- A blog post or talk they gave that is genuinely relevant
- A recent announcement (funding, product launch, hire) that creates a natural opener
- A mutual connection who can be referenced
- A specific problem their job posting or content reveals

**First-touch message format (< 100 words):**
```
[1 sentence: specific hook — why you're reaching out to them specifically]
[1 sentence: what you do and why it's relevant to them]
[1 sentence: single clear CTA — one ask, not multiple]
```

**Example (customer outreach):**
> Saw your job posting for a Head of Data — looks like you're building out your analytics stack. We help SaaS teams like [similar company] get their first dashboards live in under a day, without hiring a data engineer. Would a 20-minute call be useful?

**What not to do:**
- Do not send a 3-paragraph cold email on the first touch
- Do not ask for multiple things (demo + intro + feedback) in one message
- Do not personalize with generic observations ("I noticed you work in SaaS...")
- Do not copy-paste the same message to 50 people and call it "personalized"

## Phase 6: Output — Lead Intelligence Report

```markdown
## Lead Intelligence Report: <Campaign> | <Date>

### ICP Summary
<One paragraph: who this campaign targets and why>

### Lead List

| Name | Title | Company | Fit | Timing | Warm | Total | Priority | Hook |
|------|-------|---------|-----|--------|------|-------|----------|------|
| Jane | VP Eng | Acme | 4 | 4 | 3 | 11 | P1 | Series B announcement |
| ... |

### Priority 1 Leads (reach this week)
[Expanded profiles with personalization hooks]

### Signal Log
| Date | Signal Type | Lead | Detail |
|------|-------------|------|--------|
| ... | Job posting | Acme | Hiring 5 engineers — growth signal |

### Warm Paths Identified
- Mutual: <name> knows <lead> from <context> — ask for intro by <date>

### Follow-up Sequence Plan
- Touch 1: Email or LinkedIn DM (this week)
- Touch 2: Follow-up if no reply (+5 days)
- Touch 3: Final outreach with added value (+10 days)
- Archive if no reply after touch 3

### CRM Update
- [ ] All P1 leads added to CRM
- [ ] All outreach tracked with date sent
- [ ] Replies logged and scored
```

## Rate Limiting and Ethics

- Never use scraped data to contact people who have opted out of cold outreach
- Respect LinkedIn's ToS — do not scrape profiles at volume without consent
- Honor unsubscribe and opt-out requests immediately — log them in CRM
- Do not purchase lead lists from unverified sources (GDPR/CAN-SPAM risk)
- Maximum 3 touches per lead before archiving — no automated spray-and-pray

## Common Pitfalls

**Vague ICP produces noise** — "startup founders in tech" is not an ICP. The more specific the ICP, the higher the conversion rate. Narrow before scaling.

**Scoring without signals** — a lead with no timing signals is a cold fit match. Without urgency or intent, conversion is low regardless of fit score.

**Skipping the warm path check** — even a weak warm path (shared community mention) can double response rates. Always check before going cold.

**Generic personalization** — "I really enjoyed your LinkedIn post about leadership" is not personalization. Name the specific post and the specific point that is relevant to your pitch.

**No follow-up plan** — 80% of sales happen on follow-up, not first touch. Every outreach needs a pre-planned sequence before you send the first message.

**CRM discipline failure** — if touchpoints aren't logged, you'll double-contact, lose context, and look disorganized. Update CRM after every touch, not at the end of the week.
