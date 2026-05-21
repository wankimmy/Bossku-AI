import type { LearningEvent } from '~/types/api'

export function useLearning(status?: Ref<string>) {
  const api = useApi()
  const s = status ?? ref('')
  return useAsyncData<LearningEvent[]>(
    'learning',
    () => api.get('/learning', { status: s.value || undefined }),
    { watch: [s] },
  )
}
