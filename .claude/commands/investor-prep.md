# BosskuAI Investor Prep

Activate the `bosskuai-investor-prep` skill to build or review fundraising materials.

## Intent

Produce investor-ready materials: pitch deck structure, investor memo, financial model review, and due diligence checklist. Tailor output to the audience (VC / angel / accelerator / strategic).

## Instructions

1. Load `ai-assistant/skills/bosskuai-investor-prep/SKILL.md` before acting.
2. Clarify audience and stage before starting: VC / angel / accelerator / strategic + pre-seed / seed / Series A.
3. **Narrative first** — before any deck or memo, nail the core narrative:
   - Problem: specific, painful, large
   - Solution: concrete, differentiated
   - Market: TAM/SAM/SOM with methodology
   - Traction: real numbers, growth rate, context
   - Team: relevant domain expertise
   - Ask: specific $ + use of funds + milestones unlocked
4. For pitch deck: use `bosskuai-deep-research` to validate market size claims with citations.
5. For financial model: use `bosskuai-financial-modeling` to produce 3-year projections and unit economics.
6. For due diligence: run the investor-prep checklist at `ai-assistant/references/checklists/investor-prep-checklist.md`.
7. Identify top 10 likely investor questions and draft honest answers.

## Output

- Narrative summary (one paragraph, each sentence = one slide)
- Slide-by-slide structure with what goes on each slide
- Investor memo outline (one-pager + full memo)
- Top 10 Q&A pairs
- Due diligence gaps (what is missing or not ready)
- Next steps checklist

## Quality gates

- No vanity metrics without absolute base and context
- Market size claims cited (not "the market is $X billion" without source)
- Risks named honestly — investors will find them anyway
- Ask is specific: $ amount, use of funds, milestones unlocked
- No hockey stick without a credible mechanism
