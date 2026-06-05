import type { Run } from '~/types/api'

export function useRuns(filters?: { status?: Ref<string>; search?: Ref<string> }) {
  const api = useApi()
  const status = filters?.status ?? ref('')
  const search = filters?.search ?? ref('')

  return useAsyncData<{ data: Run[]; total?: number }>(
    'runs',
    () => api.get('/runs', { status: status.value || undefined, search: search.value || undefined }),
    { watch: [status, search] },
  )
}
