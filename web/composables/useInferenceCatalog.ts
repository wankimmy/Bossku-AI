export type InferenceModelOption = {
  id: string
  label: string
}

export type InferenceCatalog = {
  ollama: InferenceModelOption[]
  anthropic: InferenceModelOption[]
  codex: InferenceModelOption[]
  anthropic_configured: boolean
  codex_connected: boolean
}

export type InferenceOptgroup = {
  label: string
  provider: 'ollama' | 'anthropic' | 'codex'
  disabled?: boolean
  hint?: string
  options: InferenceModelOption[]
}

export function useInferenceCatalog() {
  const { data, refresh, pending, error } = useFetch<InferenceCatalog>(
    apiUrl('/settings/inference-catalog'),
    { server: false, lazy: true },
  )

  const optgroups = computed<InferenceOptgroup[]>(() => {
    const catalog = data.value
    if (!catalog) return []

    const groups: InferenceOptgroup[] = [
      {
        label: 'Ollama Cloud',
        provider: 'ollama',
        options: catalog.ollama ?? [],
      },
    ]

    const anthropicOptions = catalog.anthropic ?? []
    groups.push({
      label: 'Anthropic Claude',
      provider: 'anthropic',
      disabled: !catalog.anthropic_configured,
      hint: catalog.anthropic_configured
        ? undefined
        : 'Add an Anthropic API key below to enable Claude models.',
      options: anthropicOptions.length > 0
        ? anthropicOptions
        : [
            { id: 'claude-sonnet-4-5', label: 'Claude Sonnet 4.5 (requires API key)' },
          ],
    })

    const codexOptions = catalog.codex ?? []
    groups.push({
      label: 'Codex (ChatGPT)',
      provider: 'codex',
      disabled: !catalog.codex_connected,
      hint: catalog.codex_connected
        ? undefined
        : 'Connect with ChatGPT below to enable Codex models.',
      options: codexOptions.length > 0
        ? codexOptions
        : [
            { id: 'gpt-4o', label: 'GPT-4o (requires connection)' },
          ],
    })

    return groups
  })

  const allModelIds = computed(() => {
    return optgroups.value.flatMap(g => g.options.map(o => o.id))
  })

  return {
    catalog: data,
    optgroups,
    allModelIds,
    refresh,
    pending,
    error,
  }
}
