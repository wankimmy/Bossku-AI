# Lead Intelligence

## When to use
- User wants to find specific leads: investors, customers, partners, or press contacts
- "Who should I reach out to for X?" or "Find me 20 potential enterprise customers"
- Building an outreach list for a launch, fundraise, or partnership campaign
- Qualifying an existing list of companies or contacts
- Researching a specific person or company before a meeting
- Finding warm introduction paths to a target contact

## How this differs from nearby skills
- **vs sales-strategy:** sales-strategy defines the ICP, pipeline stages, and overall go-to-market motion. lead-intelligence executes actual discovery and qualification against a defined ICP — it answers "who specifically?" not "what kind of person?"
- **vs marketing-growth:** marketing-growth plans channels and campaigns. lead-intelligence finds specific named individuals and companies to contact.
- **vs market-analysis:** market-analysis maps the competitive landscape. lead-intelligence finds actionable contacts within that landscape.
- **vs deep-research:** deep-research conducts broad topic research. lead-intelligence is focused, structured prospecting with scoring and outreach drafts.

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
Apply signal weights to rank results:

| Signal | Weight |
|---|---|
| Role match (title, seniority, department) | 30% |
| Industry / vertical match | 25% |
| Recency of activity / signal | 20% |
| Influence / reach (followers, company size) | 15% |
| Geography / location fit | 10% |

Score each lead 0-100. Tier: A (80-100), B (60-79), C (40-59). Default to Tier A leads for outreach.

### 4. Find warm paths
For each Tier A lead:
- Mutual LinkedIn connections (manual check or Playwright)
- Shared communities, Slack groups, alumni networks, investors in common
- Prior interactions (commented on same post, attended same event)
- Portfolio company connections if targeting investors
Document any warm path found — warm outreach converts 3-5x better than cold.

### 5. Draft personalized outreach per channel
For each Tier A lead, produce a draft message:

| Channel | Format |
|---|---|
| Email | Subject line + 3-sentence body (problem relevance, social proof, CTA) |
| LinkedIn DM | 2-sentence connection request + 2-sentence follow-up |
| Twitter/X | Brief, public reply or DM referencing shared context |
| Intro request | Message to mutual connection requesting warm intro |

Personalize each draft to the specific lead's signals — no generic templates in final output.

## Output format

**Ranked lead list:**
| Rank | Name | Title | Company | Score | Tier | Warm Path | Source |
|---|---|---|---|---|---|---|---|

**Per-lead detail (Tier A):**
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
