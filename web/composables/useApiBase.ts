/** External API host (empty = same-origin `/api/...` via Nitro proxy). */
export function useApiBase(): string {
  const config = useRuntimeConfig()
  const raw = (config.public.apiBase as string) ?? ''

  return String(raw).replace(/\/$/, '')
}

/** Build `/api/...` or `{apiBase}/api/...` for fetch / useFetch. */
export function apiUrl(path: string): string {
  const base = useApiBase()
  const normalized = path.startsWith('/') ? path : `/${path}`
  const full = normalized.startsWith('/api') ? normalized : `/api${normalized}`
  if (!base) return full

  return `${base}${full}`
}
