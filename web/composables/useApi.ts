export function useApi() {
  const config = useRuntimeConfig()
  const base = (config.public.apiBase as string) || 'http://localhost:8000'

  return {
    get: (path: string, params?: Record<string, unknown>) =>
      $fetch(`${base}/api${path}`, { params }),
    post: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'POST', body }),
    put: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'PUT', body }),
    patch: (path: string, body?: unknown) =>
      $fetch(`${base}/api${path}`, { method: 'PATCH', body }),
    del: (path: string) =>
      $fetch(`${base}/api${path}`, { method: 'DELETE' }),
  }
}
