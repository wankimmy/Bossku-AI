import type { Plugin } from '~/types/api'

export function usePlugins() {
  const api = useApi()
  return useAsyncData<Plugin[]>('plugins', () => api.get('/plugins'))
}
