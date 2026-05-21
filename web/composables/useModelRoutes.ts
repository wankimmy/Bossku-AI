import type { ModelRoute } from '~/types/api'

export function useModelRoutes() {
  const api = useApi()
  return useAsyncData<ModelRoute[]>('model-routes', () => api.get('/model-routes'))
}
