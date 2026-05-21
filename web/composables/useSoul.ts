import type { SoulVersion } from '~/types/api'

export function useSoul() {
  const api = useApi()
  const active = useAsyncData<SoulVersion>('soul', () => api.get('/soul'))
  const history = ref<SoulVersion[]>([])
  const suggestions = ref<unknown[]>([])

  async function loadHistory() {
    history.value = (await api.get('/soul/history')) as SoulVersion[]
  }

  async function loadSuggestions() {
    suggestions.value = (await api.get('/soul/suggestions')) as unknown[]
  }

  async function saveSoul(content: string) {
    return api.put('/soul', { content })
  }

  return { ...active, history, suggestions, loadHistory, loadSuggestions, saveSoul }
}
