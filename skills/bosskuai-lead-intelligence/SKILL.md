---
name: bosskuai-lead-intelligence
description: "Use this for named-contact research: investor, partner, press, and key-account contact lists, pre-meeting research on a person or company, warm-intro paths, outreach drafts, and the pre-send deliverability and consent check."
---

# Lead Intelligence

## When to use
- User wants to find specific leads: investors, customers, partners, or press contacts
- "Who should I reach out to for X?" or "Find the right person to contact at these 20 target accounts"
- Building an outreach list for a launch, fundraise, or partnership campaign
- Qualifying an existing list of companies or contacts
- Researching a specific person or company before a meeting
- Finding warm introduction paths to a target contact

## How this differs from nearby skills
- **vs sales-strategy:** sales-strategy defines the ICP, pipeline stages, and overall go-to-market motion. lead-intelligence executes actual discovery and qualification against a defined ICP — it answers "who specifically?" not "what kind of person?"
- **vs marketing-growth:** marketing-growth plans channels and campaigns. lead-intelligence finds specific named individuals and companies to contact.
- **vs market-analysis:** market-analysis maps the competitive landscape. lead-intelligence finds actionable contacts within that landscape.
- **vs deep-research:** deep-research conducts broad topic research. lead-intelligence is focused, structured prospecting with scoring and outreach drafts.
- **vs prospecting:** prospecting builds and verifies account-level customer lists at volume (SaaS, B2B, local SMB, demand-signal branches; email verification; CSV). This skill goes to named people: investor, partner, press, and key-account contacts, pre-meeting research, and warm intros.
- **vs revops:** revops scores inbound leads in the CRM (fit + engagement, MQL threshold); this skill scores an outbound research list.

## MCP requirements
- **Exa (required):** Primary tool for finding matching people and companies via semantic and structured search. This skill has significantly reduced capability without Exa.
- **Playwright (optional):** Social profile research, company page scraping for signals. Graceful degradation: skip social signals layer if Playwright unavailable.
- Graceful degradation without Exa: provide a search strategy and query templates the user can run manually.

## Workflow

### 1. Define the ideal contact profile
Before searching, produce a precise profile:
- **Role signals:** Job titles, seniority (VP+, founder, head of), department
- **Company signals:** Industry, company size (ARR or headcount), stage (seed/Series A/enterprise), geography
- **Behavioral signals:** Recent funding, hiring in relevant areas, posted about the problem, attended relevant events
- **Exclusions:** Competitor employees, conflicted investors, regions not served

Output this as a one-paragraph ICP statement before proceeding.

### 2. Search via Exa
Construct targeted Exa searches:
- Person searches: "[role] at [industry] companies", "[title] who [behavior signal]"
- Company searches: "[industry] companies using [technology]", "[industry] Series A [geography]"
- News/signal searches: "[company] recently hired [role]", "[person] posted about [problem]"
Run multiple searches with varied query angles to avoid single-source bias.

### 3. Score each lead
Score 1-5 on three dimensions and add them:
- **Fit**: role, seniority, company size, industry, and stage against the ICP. Fit below 3 = do not contact, whatever the other scores.
- **Timing**: a dated intent signal (funding, hiring in the relevant area, launch, a post about the problem).
- **Warm path**: 5 direct mutual who will intro, 4 two degrees through a strong tie, 3 shared community you can name, 2 engaged with shared content, 1 none.
Total 12-15 = P1 (this week), 9-11 = P2 (this month), 6-8 = P3 (nurture), below 6 = drop. Default outreach to P1.

### 4. Find warm paths
For each P1 lead:
- Mutual LinkedIn connections (manual check or Playwright)
- Shared communities, Slack groups, alumni networks, investors in common
- Prior interactions (commented on same post, attended same event)
- Portfolio company connections if targeting investors
Document any warm path found; it decides the channel.

### 5. Draft personalized outreach per channel
For each P1 lead, produce a draft message:

| Channel | Format |
|---|---|
| Email | Subject line + 3-sentence body (problem relevance, social proof, CTA) |
| LinkedIn DM | 2-sentence connection request + 2-sentence follow-up |
| Twitter/X | Brief, public reply or DM referencing shared context |
| Intro request | Message to mutual connection requesting warm intro |

Personalize each draft to the specific lead's signals — no generic templates in final output.

## Output format

**Ranked lead list:**
| Rank | Name | Title | Company | Fit | Timing | Warm | Total | Priority | Warm path | Source |
|---|---|---|---|---|---|---|---|---|---|---|

**Per-lead detail (P1):**
- Contact info (LinkedIn URL, email if found)
- Score breakdown by signal
- Warm path details
- Draft outreach message (channel-specific)

**Search queries used** (for reproducibility)

## Guardrails
- Never auto-send any message. All outreach is drafted for user review and approval before sending.
- Do not scrape private data (private social profiles, email lists obtained without consent).
- Do not compile lists of personal data beyond what is needed for the stated outreach purpose.
- Flag any contact where outreach may be legally sensitive (regulated industries, jurisdiction-specific rules).
- If Exa returns irrelevant results, refine queries and state what was changed — do not pad the list with low-quality leads.
- Minimum viable list: 10 quality Tier A leads is better than 100 unscored contacts.

## Send gate (before anything goes out)

Drafts stay drafts until each line is confirmed; copy work goes to `cold-email`.
- Authentication: SPF or DKIM on the sending domain for any Gmail volume. Above 5,000 messages a day to Gmail or Outlook.com consumer inboxes: SPF and DKIM and DMARC (p=none is enough), with the From: domain aligned.
- Spam rate in Gmail Postmaster Tools: keep under 0.10%, never reach 0.30%.
- Every commercial email: accurate headers and subject, a clear disclosure that it is an ad, the sender's physical postal address, and an opt-out honoured within 10 business days (US CAN-SPAM has no B2B exemption). Bulk marketing mail also needs one-click unsubscribe plus a visible unsubscribe link.
- The recipient's country sets the consent rule: EU/UK and Canada are stricter than CAN-SPAM (`../prospecting/references/compliance.md`); for Malaysian contacts load `bosskuai-malaysia-pdpa-privacy`.
- Keep source URL and date per contact, and suppress every opt-out from future lists.

## Further reading

- `../../references/playbooks/lead-intelligence-detailed-playbook.md` — extended step-by-step workflow and detailed templates that complement this playbook.
