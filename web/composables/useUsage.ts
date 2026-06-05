import type { UsageEvent, UsageSummary } from '~/types/api'

export function useUsage() {
  const api = useApi()
  const summary = useAsyncData<UsageSummary>('usage-summary', () => api.get('/usage/summary'))
  const events = useAsyncData<UsageEvent[]>('usage', () => api.get('/usage'))

  return { summary, events }
}
