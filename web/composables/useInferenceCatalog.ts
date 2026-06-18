export type InferenceModelOption = {
  id: string
  label: string
  score?: number
  auto_selected?: boolean
}

export type InferenceProviderGroup = {
  provider: string
  name: string
  auth: 'api_key' | 'oauth'
  configured: boolean
  disabled?: boolean
  hint?: string
  all_cloud_models: InferenceModelOption[]
  recommended_models: InferenceModelOption[]
}

export type InferenceCatalog = {
  version?: string
  cloud_only?: boolean
  providers?: InferenceProviderGroup[]
  ollama: InferenceModelOption[]
  anthropic: InferenceModelOption[]
  codex: InferenceModelOption[]
  anthropic_configured: boolean
  codex_connected: boolean
}

export type InferenceOptgroup = {
  label: string
  provider: string
  disabled?: boolean
  hint?: string
  options: InferenceModelOption[]
}

export type RoleRecommendation = {
  role: string
  provider?: string
  recommended_models: InferenceModelOption[]
  auto_selected?: string | null
}

export function useInferenceCatalog() {
  const { data, refresh, pending, error } = useFetch<InferenceCatalog>(
    apiUrl('/settings/inference-catalog'),
    { server: false, lazy: true },
  )

  const providerGroups = computed<InferenceProviderGroup[]>(() => data.value?.providers ?? [])

  const optgroups = computed<InferenceOptgroup[]>(() => {
    const catalog = data.value
    if (!catalog) return []

    if (catalog.providers && catalog.providers.length > 0) {
      return catalog.providers.map(g => ({
        label: g.name,
        provider: g.provider,
        disabled: g.disabled ?? !g.configured,
        hint: g.hint,
        options: g.recommended_models.length > 0
          ? g.recommended_models.map(m => ({ id: m.id, label: m.label }))
          : g.all_cloud_models.slice(0, 3),
      }))
    }

    const groups: InferenceOptgroup[] = [
      { label: 'Ollama Cloud', provider: 'ollama-cloud', options: catalog.ollama ?? [] },
    ]

    groups.push({
      label: 'Anthropic Claude',
      provider: 'anthropic',
      disabled: !catalog.anthropic_configured,
      hint: catalog.anthropic_configured ? undefined : 'Add an Anthropic API key in Settings → Providers.',
      options: catalog.anthropic?.length ? catalog.anthropic : [{ id: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6 (requires API key)' }],
    })

    groups.push({
      label: 'Codex (ChatGPT)',
      provider: 'codex',
      disabled: !catalog.codex_connected,
      hint: catalog.codex_connected ? undefined : 'Connect with ChatGPT in Settings → Models.',
      options: catalog.codex?.length ? catalog.codex : [{ id: 'gpt-5.5', label: 'GPT-5.5 (requires connection)' }],
    })

    return groups
  })

  const allModelIds = computed(() => optgroups.value.flatMap(g => g.options.map(o => o.id)))

  async function fetchRecommendations(role: string, provider?: string): Promise<RoleRecommendation> {
    const params = new URLSearchParams({ role })
    if (provider) params.set('provider', provider)
    return await $fetch<RoleRecommendation>(apiUrl(`/settings/model-recommendations?${params}`))
  }

  async function applyRecommendedDefaults(): Promise<Record<string, { provider: string, model: string, score: number }>> {
    const res = await $fetch<{ applied: Record<string, { provider: string, model: string, score: number }> }>(
      apiUrl('/settings/model-recommendations/apply'),
      { method: 'POST' },
    )
    return res.applied
  }

  return {
    catalog: data,
    providerGroups,
    optgroups,
    allModelIds,
    refresh,
    pending,
    error,
    fetchRecommendations,
    applyRecommendedDefaults,
  }
}
