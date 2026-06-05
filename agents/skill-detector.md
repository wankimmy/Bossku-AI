# Skill detector

Use this file to fill the **`Skill:`** field in the `[BOSSKUAI]` indicator (orchestrator pass or any single-call reply).

Orchestrator: detect **`Skill`** for the `[BOSSKUAI]` line using keywords in the **user prompt** (and repo path hints only as tie-breakers). Prefer one primary skill; add one secondary **only if** clearly needed (`laravel + docker`).

## Mapping

| Keywords / cues | Skill |
|---|---|
| Laravel, PHP, Eloquent, Filament, Inertia, Octane, queue, migration | **laravel** |
| Nuxt, Vue, SSR, SPA, Pinia, Tailwind, frontend | **nuxt** |
| Docker, VPS, Nginx, deployment, container, compose, SSL | **docker** |
| SQL, MySQL, MariaDB, PostgreSQL, SQLite, Redis, MongoDB | **database** |
| OWASP, auth, permission, roles, access control, vulnerability | **security** |
| Landing page, mobile responsive, UI, UX, design, dashboard | **ui-ux** |
| SEO, GEO, content ranking, search visibility, metadata | **seo-geo** |
| Unit test, Pest, PHPUnit, Playwright, CI/CD, GitHub Actions | **testing** |
| Roadmap, SaaS pricing, features, user flow, MVP | **product-strategy** |
| iOS, Android, React Native, Flutter, mobile app (native) | **mobile** |
| ETL, dbt, Airflow, BigQuery, data warehouse, ingestion pipeline | **data-engineering** |
| Model training, fine-tuning, embeddings pipeline, eval harness, offline metrics | **ml** |
| Prometheus, Grafana, OpenTelemetry, SLO, on-call, alerting, log pipeline | **observability** |
| Unknown or overly broad request | **general** |

### Load these Bossku skills after the label

| Detector label | Primary skill | Secondary (when needed) |
|---|---|---|
| **mobile** | `bosskuai-engineering-delivery` | `bosskuai-ui-ux-design-to-code` |
| **data-engineering** | `bosskuai-database-engineering` | — |
| **ml** | `bosskuai-eval-driven-agent-improvement` | Repo evidence before strong claims |
| **observability** | `bosskuai-observability-sre` | — |

Resolve conflicts by **risk** then **intent**: auth/security cues → prefer **security** if in doubt vs generic **laravel**; DB schema vs app code vs **database**.

## Loop & alignment cues (cross-cutting — pair with the domain skill)

These are *phase* skills layered on top of the domain skill above, not replacements. They carry the **loop-until-fixed** discipline every agent now follows.

| Keywords / cues | Skill |
|---|---|
| diagnose, debug, broken, throwing, failing, flaky, perf regression, "why is this happening" | `bosskuai-diagnose-loop` |
| write tests first, TDD, red-green-refactor, tracer bullet, regression test | `bosskuai-tdd-loop` |
| build failing, typecheck error, lint error, CI red, dependency conflict | `bosskuai-diagnose-loop` (via build-fixer) |
| review the PR/MR, greploop, fix all comments, "until 5/5", resolve threads | `bosskuai-greptile-review-loop` |
| check the PR/MR, unresolved comments, prepare for merge, failing checks | `bosskuai-pr-check` |
| grill me, stress-test my plan, interview me, challenge this design | `bosskuai-grill-me` (or `bosskuai-grill-with-docs` if the repo has CONTEXT.md/ADRs) |
| improve architecture, deepen modules, reduce coupling, ball of mud, make testable | `bosskuai-architecture-deepening` |
| zoom out, bigger picture, map this area, "I don't know this code" | `bosskuai-zoom-out` |
| prototype, "let me play with it", sanity-check the state machine, try a few designs | `bosskuai-throwaway-prototype` (demos/MVP → `bosskuai-rapid-prototype`) |
| hand off, compact this conversation, fresh session pickup | `bosskuai-handoff` |

Domain label sets the **primary** skill; the loop cue sets the **secondary** (e.g. `laravel + bosskuai-diagnose-loop`). When the request is purely a loop activity (e.g. "greploop this PR"), the loop skill is primary.

## Cross-reference

Load deep playbooks via root [`skills/`](../skills/) summaries → `ai-assistant/skills/` + `skill-index.json`.
