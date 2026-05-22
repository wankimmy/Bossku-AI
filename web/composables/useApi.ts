import { apiUrl } from '~/composables/useApiBase'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'

export function useApi() {
  const headers = () => apiAuthHeaders()

  return {
    get: (path: string, params?: Record<string, unknown>) =>
      $fetch(apiUrl(path), { params, headers: headers() }),
    post: (path: string, body?: unknown) =>
      $fetch(apiUrl(path), { method: 'POST', body, headers: headers() }),
    put: (path: string, body?: unknown) =>
      $fetch(apiUrl(path), { method: 'PUT', body, headers: headers() }),
    patch: (path: string, body?: unknown) =>
      $fetch(apiUrl(path), { method: 'PATCH', body, headers: headers() }),
    del: (path: string) =>
      $fetch(apiUrl(path), { method: 'DELETE', headers: headers() }),
  }
}
