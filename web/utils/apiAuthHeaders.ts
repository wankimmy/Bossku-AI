export function apiAuthHeaders(): Record<string, string> {
  const config = useRuntimeConfig()
  const token = (config.public.apiToken as string) || ''
  if (!token) return {}
  return { Authorization: `Bearer ${token}` }
}
