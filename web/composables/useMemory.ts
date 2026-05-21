export function useMemory() {
  const api = useApi()
  return useAsyncData('memory', () => api.get('/memory'))
}
