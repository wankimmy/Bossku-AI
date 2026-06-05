import type { BrainData } from '~/types/api'

export function useBrain() {
  const api = useApi()
  return useAsyncData<BrainData>('brain', () => api.get('/brain'))
}
