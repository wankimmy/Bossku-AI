# Agent activity UI spec

Goal: show **what agents are doing now** and **what happened recently** without reading raw logs.

## Primary view

```text
Agent Activity
├── Current task
├── Selected skill
├── Selected model
├── Current agent
├── Memory used or not
├── Files inspected
├── Files changed
├── Tests run
├── Audit status
└── Final review status
```

## Example row (run header)

```text
Current Agent: Executor
Skill: Laravel + Docker
Model: Kimi K2.6
Memory Used: Yes
Status: Modifying docker-compose.yml and Laravel queue config
```

## Map to current Nuxt MVP

| UI block | Implementation hook |
|---|---|
| Current task + status | [`web/components/ExecutionTimeline.vue`](../web/components/ExecutionTimeline.vue) stages / SSE payload |
| Routing + models | [`web/components/RoutingDashboard.vue`](../web/components/RoutingDashboard.vue) + `Run.metadata.routing_decision` |
| Run detail page | [`web/pages/runs/[id].vue`](../web/pages/runs/[id].vue) |
| Index of runs | [`web/pages/runs/index.vue`](../web/pages/runs/index.vue) |

## UX requirements

- Timeline can collapse completed stages; emphasize **active** stage badge
- Files inspected/changed require **click-to-copy** paths
- Audit + final review statuses use traffic colors (pass / notes / fail)
- Mobile: stack layout; sticky summary header

## Gaps to close

1. Surface **explicit “Memory Used: Yes/No”** from orchestrator metadata (currently infer from timeline copy).
2. List **tests run** parsed from auditor/final payloads or CI hook (string array).
3. Add **skill** badge consistent with detector vocabulary (`agents/skill-detector.md`).
