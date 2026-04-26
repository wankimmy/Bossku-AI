# BosskuAI Expert Benchmark Suite

This benchmark suite raises the bar from “the skill exists” to “the workspace contains the right expert guidance for the task.”

## What it tests

`evals/expert-benchmark-cases.json` contains representative expert cofounder tasks for:

- Laravel backend audit
- Nuxt SSR/SEO/performance audit
- VPS Docker deployment hardening
- MariaDB/MySQL/PostgreSQL/SQLite schema/index review
- MongoDB document modeling
- Redis cache/queue operations
- Humanized UI/UX and anti-AI design review
- Security threat modeling
- SEO/GEO landing-page audit
- 90-day content calendar
- Sales/GTM cofounder review
- full cofounder-agent stack audit

Run:

```bash
python3 -S scripts/eval_expert_coverage.py
```

## How to interpret

- **Routing pass** means the prompt selects the expected skill.
- **Reference pass** means the expected playbook/checklist exists.
- **Coverage pass** means the required expert terms are present in the skill + reference corpus.

This is still a proxy test. It does not prove the LLM will always answer expertly. It prevents the repo from claiming expert coverage while missing the actual guidance.

## 4.5/5 threshold

A package can claim 4.5/5 only if:

1. `scripts/check-workspace.sh . --profile full` passes.
2. `scripts/validate-skill-index.sh .` passes.
3. `scripts/verify-skill-references.sh .` passes.
4. `python3 -S scripts/eval_workspace.py` passes with all routing, retrieval, and workflow proxy cases.
5. `python3 -S scripts/eval_expert_coverage.py` passes all expert coverage cases.
6. New skills include at least one real task prompt and one coverage case.
