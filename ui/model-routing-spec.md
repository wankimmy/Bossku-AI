# Model routing UI spec

Shows **why** Bossku chose a workflow + stack of models — transparency for operators.

## User stories

1. As a developer, I can see **planned vs actual models** after a run completes.
2. As an admin, I can toggle **cheap router classifier** offline for air-gapped work.
3. As a reviewer, I can drill into **fallback reasons** without reading Laravel logs verbatim.

## Map to existing UI

| Need | Surface |
|---|---|
| Router LLM on/off | [`web/pages/settings.vue`](../web/pages/settings.vue) (`routing_llm_enabled`) |
| Decided models + cascade | [`web/components/RoutingDashboard.vue`](../web/components/RoutingDashboard.vue) embedded in [`web/pages/runs/[id].vue`](../web/pages/runs/[id].vue) |
| Narrative routing rules | Docs link-out: [`../agents/model-router.md`](../agents/model-router.md) |

## Proposed augmentations

1. Add **comparison table**:

   ```text
   Stage | Planned model | Fallback used | Trigger
   ```

2. Add **risk chip** synced with backend `risk_level`.
3. Show **estimated token tier** (`low|medium|high`) separately from latency.

## Non-goals

- Replacing billing dashboards from vendors
- Showing exact per-stage dollar cost (often unknown) unless real telemetry exists (`scripts/bosskuai eval token` optional link)
