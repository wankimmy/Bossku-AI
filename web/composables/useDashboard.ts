export function useDashboard() {
  const api = useApi()
  return useAsyncData('dashboard', () => api.get('/dashboard'))
}
