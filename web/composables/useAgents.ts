import type { Agent } from '~/types/api'

export function useAgents() {
  const api = useApi()
  return useAsyncData<Agent[]>('agents', () => api.get('/agents'))
}
