# References by division

Use this table to find checklists and playbooks by division. Skills in AGENTS.md map to these references for structured outputs and quality gates.

**Rules vs skills vs references:** Working rules in **AGENTS.md** define what to do and when to load which skill. **Skills** define when to load and how to think (workflow and lens). **Checklists and playbooks** in this folder define how to execute a task and what to produce; use them for quality gates and copy-paste friendly outputs.

| Division | Checklists | Playbooks |
|----------|-------------|-----------|
| **Orchestration** | project-understanding-checklist, learning-promotion-checklist, continuous-learning-checklist, search-first-checklist, skill-health-checklist, rule-distillation-checklist | project-understanding-playbook, continuous-learning-playbook, search-first-playbook, skill-stocktake-playbook, rules-distillation-playbook |
| **Product** | product-spec-checklist, planning-checklist, analytics-metrics-checklist | product-discovery-playbook, planning-playbook, analytics-metrics-playbook |
| **Product / PM** | project-management-checklist | project-management-playbook |
| **Engineering** | engineering-delivery-checklist, coding-best-practices-checklist, code-revamp-checklist, verification-checklist, devops-iac-checklist | engineering-delivery-playbook, coding-best-practices-playbook, code-revamp-playbook, verification-playbook, devops-iac-playbook |
| **Engineering** | codebase-analysis-checklist, polyglot-engineering-checklist | codebase-analysis-playbook, polyglot-engineering-playbook |
| **Design** | ui-fidelity-checklist, 3d-web-development-checklist, i18n-l10n-checklist | ui-delivery-playbook, 3d-web-development-playbook, i18n-l10n-playbook |
| **Security** | security-risk-checklist, agent-security-hardening-checklist, legal-compliance-checklist | security-review-playbook, agent-security-hardening-playbook, legal-compliance-playbook |
| **Quality** | bug-finding-checklist, business-logic-checklist | bug-finding-playbook |
| **Architecture** | architecture-review-checklist, api-design-checklist, data-architecture-checklist | architecture-playbook, api-design-playbook, data-architecture-playbook |
| **Marketing** | marketing-growth-checklist, social-content-calendar-checklist, paid-acquisition-monetization-checklist | marketing-growth-playbook, social-content-calendar-playbook, paid-acquisition-monetization-playbook |
| **Sales** | sales-strategy-checklist | sales-strategy-playbook |
| **Launch** | launch-commercialization-checklist | launch-commercialization-playbook |
| **SEO/GEO** | seo-geo-checklist | seo-geo-playbook |
| **Market** | market-analysis-checklist | market-research-playbook |
| **Continuation** | context-limit-continuation-checklist | — |
| **AI ops** | model-selection-checklist | model-selection-playbook |

Other:

- **Rigorous code review** (`bosskuai-rigorous-code-review` skill): uses `coding-best-practices-checklist`, `verification-checklist`, `bug-finding-checklist`, and `architecture-review-checklist` as review gates.
- **Session handoff**: `session-handoff-template.md`
- **Pitfalls**: `pitfalls/` — see `pitfalls/README.md` (domain files: security, performance, business-logic, product, ai-workspace + `general-known-pitfalls.md`)
- **ADRs**: `adr/` — see `adr/README.md` for index
- **Skill reference integrity**: from repo root, `./scripts/verify-skill-references.sh` checks that every `../../references/...` link in `skills/**/SKILL.md` resolves
