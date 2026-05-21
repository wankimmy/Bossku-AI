import type { RoutingSummary } from '~/types/bossku'

export type PublicSettings = {
  planner_model?: string
  orchestrator_model?: string
  executor_model?: string
  auditor_model?: string
  router_model?: string
}

/** Configured Ollama role models from GET /api/settings (shown when no run routing yet). */
export function useConfiguredRouting() {
  const configured = ref<RoutingSummary | null>(null)
  const api = useApi()

  async function load() {
    try {
      const s = await api.get<PublicSettings>('/settings')
      configured.value = {
        backend: 'Ollama',
        reasoningModel: s.orchestrator_model || s.planner_model,
        codingModel: s.executor_model,
        reviewModel: s.auditor_model,
        fastModel: s.router_model || s.planner_model,
      }
    }
    catch {
      configured.value = null
    }
  }

  onMounted(() => {
    load()
  })

  return { configured, refreshConfigured: load }
}

export function mergeRoutingSummary(
  runtime: RoutingSummary,
  configured: RoutingSummary | null,
): RoutingSummary {
  if (!configured) return runtime

  return {
    ...runtime,
    reasoningModel: runtime.reasoningModel ?? configured.reasoningModel,
    codingModel: runtime.codingModel ?? configured.codingModel,
    reviewModel: runtime.reviewModel ?? configured.reviewModel,
    fastModel: runtime.fastModel ?? configured.fastModel,
  }
}
