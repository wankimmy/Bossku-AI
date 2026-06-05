import type { LlmProvider } from '~/types/api'

export function useProviders() {
  const api = useApi()
  return useAsyncData<LlmProvider[]>('providers', () => api.get('/providers'))
}
