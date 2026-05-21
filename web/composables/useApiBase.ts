export function useApiBase(): string {
  const config = useRuntimeConfig()

  return (config.public.apiBase as string) || 'http://localhost:28480'
}
