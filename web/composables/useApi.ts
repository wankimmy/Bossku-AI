import { apiAuthHeaders } from '~/utils/apiAuthHeaders'

export function useApi() {
  const config = useRuntimeConfig()
  const base = (config.public.apiBase as string) || 'http://localhost:28480'
  const headers = () => apiAuthHeaders()

  return {
    get: (path: string, params?: Record<string, unknown>) =>
      $fetch(`${base}/api${path}`, { params, headers: headers() }),
    post: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'POST', body, headers: headers() }),
    put: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'PUT', body, headers: headers() }),
    patch: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'PATCH', body, headers: headers() }),
    del: (path: string) =>
      $fetch(`${base}/api${path}`, { method: 'DELETE', headers: headers() }),
  }
}
