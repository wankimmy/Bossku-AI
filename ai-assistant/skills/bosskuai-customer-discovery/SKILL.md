---
name: bosskuai-customer-discovery
description: Use this skill for user interview planning, customer discovery, survey design, interview transcript analysis, and persona building from primary research. Gathers and structures the evidence that product strategy acts on.
---

# BosskuAI Customer Discovery

## When to use

- Planning user interviews (who to recruit, what to ask)
- Designing a discovery survey or screener
- Analysing interview transcripts for patterns
- Building evidence-backed personas from primary research
- Validating or invalidating a product hypothesis with real users
- Extracting Jobs-to-be-Done (JTBD) from qualitative data
- Synthesising community feedback (Reddit, reviews, forums) into structured insight

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: synthesises all evidence (customer, market, competitive) into what to build and why. Customer discovery gathers primary evidence from users — it is the input, not the conclusion.
- **`bosskuai-market-analysis`**: works with secondary market data (analyst reports, search volume, public signals). Customer discovery works with primary data — direct user conversations and first-hand observations.
- **`bosskuai-deep-research`**: broad multi-source investigation. Customer discovery is specifically about understanding specific users and their behaviours, motivations, and unmet needs.

## MCP requirements

| Tool | Role | Degradation |
|------|------|-------------|
| Exa | Finding relevant communities, forums, review sites (optional) | Without Exa, rely on user-supplied transcript or survey data |
| Firecrawl | Scraping review sites (G2, Capterra, Reddit threads) for existing signal (optional) | Without Firecrawl, manually paste review excerpts |

## Workflow

1. **Define the hypothesis to test** — State exactly what belief you are trying to validate or invalidate. Example: "B2B ops managers spend 3+ hours/week on manual reporting and would pay to automate it." Be specific about the segment and the behaviour.

2. **Identify the target segment** — Define: company size, role/title, industry, geography, and any qualifying behaviour (e.g., "currently using spreadsheets for X"). A too-broad segment produces unfocused findings.

3. **Design the interview script** — Apply JTBD principles:
   - Open with context questions (tell me about your role, your team, a typical week).
   - Focus on past behaviour, not hypothetical future behaviour ("tell me about the last time you...").
   - Avoid leading questions ("do you find X frustrating?" → "walk me through how you handle X").
   - Use the "five whys" to surface root motivations.
   - End with: "Is there anything I didn't ask that you think is important?"
   - Target 45–60 minutes per session; 8–12 interviews per segment to reach pattern saturation.

4. **Analyse transcripts for patterns** — For each transcript: tag quotes by theme. After all interviews: count theme frequency, note intensity (mild mention vs. strong emotion), and flag outliers. Look for: recurring pain triggers, existing workarounds, decision criteria, switching triggers.

5. **Synthesise into persona and insight cards** — For each distinct user type identified:
   - Persona card: role, context, primary JTBD, top 3 pains, top 3 goals, current solutions.
   - Insight cards: finding + evidence count + confidence level + product implication.

6. **Map findings to product implications** — For each validated insight: state the specific product or positioning decision it informs. Separate observations (what users said/did) from interpretations (what it means).

## Output format

### Persona card
```
## Persona: [Name / Archetype]
- Role and context: ...
- Primary JTBD: "When I [situation], I want to [motivation] so I can [outcome]."
- Top pains: (1) ... (2) ... (3) ...
- Top goals: (1) ... (2) ... (3) ...
- Current solutions / workarounds: ...
- Quote: "..."
```

### Insight matrix
| Finding | Evidence count | Confidence | Product implication |
|---------|---------------|------------|---------------------|
| Users manually export data to Excel weekly | 7/10 interviews | HIGH | Automate export / native reporting is table stakes |
| Price sensitivity below $50/user/month | 4/10, mixed intensity | MEDIUM | Test pricing at $39 before $59 |

### Interview script template
Included in the playbook.

## Guardrails

- Never treat n=1 as validated — one enthusiastic user is not a pattern.
- Separate observation (verbatim quote, described behaviour) from interpretation (your conclusion) at all times.
- Label sample size on every finding — "6 of 9 interviews" not "most users."
- Minimum 5 interviews before drawing pattern-level conclusions; 8–12 for a new segment.
- Avoid confirmation bias — actively look for disconfirming evidence.
- Do not lead the interview toward your hypothesis — test it, don't sell it.
- Synthesised personas must map to actual interview data, not archetypes invented without evidence.
- If scraping community forums, distinguish aggregated public signals from direct interview evidence — they have different confidence weights.
