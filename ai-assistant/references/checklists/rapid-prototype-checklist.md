# Rapid Prototype Checklist

## Pre-work
- [ ] Timebox confirmed: hard deadline agreed
- [ ] Success criteria defined: "Done when someone can [demo action]"
- [ ] Audience defined: internal only / investor demo / user test
- [ ] Hard-scope (must-have) vs soft-scope (nice-to-have) separated

## Tech-debt ledger (fill before coding)
- [ ] All planned shortcuts listed explicitly:
  - [ ] Hardcoded values (list them)
  - [ ] Skipped error handling (list paths)
  - [ ] No auth / mock auth (confirm acceptable)
  - [ ] No tests (confirm acceptable for timebox)
- [ ] Debt ledger reviewed with stakeholder before starting

## During build
- [ ] Building in priority order (critical path first)
- [ ] Flagged if timebox at 50% with < 50% done
- [ ] Not gold-plating: resisting unnecessary improvements

## Demo readiness
- [ ] Happy path works end-to-end
- [ ] Demo script written (3–5 minute walk-through)
- [ ] Known failure points documented
- [ ] Next steps and debt-paydown plan drafted

## Quality gate
- [ ] Prototype labeled "PROTOTYPE / DO NOT SHIP" in code comments
- [ ] Debt ledger is complete and shared with team
- [ ] No prototype code merged to production branch without explicit review
