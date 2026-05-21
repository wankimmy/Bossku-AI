import type { FeedbackItem } from '~/types/api'

export function useFeedback(filters?: { signal?: Ref<string>; targetType?: Ref<string> }) {
  const api = useApi()
  const signal = filters?.signal ?? ref('')
  const targetType = filters?.targetType ?? ref('')

  return useAsyncData<FeedbackItem[]>(
    'feedback',
    () => api.get('/feedback', {
      signal: signal.value || undefined,
      target_type: targetType.value || undefined,
    }),
    { watch: [signal, targetType] },
  )
}
