import type { Approval } from '~/types/api'

export function useApprovals(filters?: { status?: Ref<string>; runId?: Ref<string> }) {
  const api = useApi()
  const status = filters?.status ?? ref('')
  const runId = filters?.runId ?? ref('')

  return useAsyncData<Approval[]>(
    'approvals',
    () => api.get('/approvals', {
      status: status.value || undefined,
      run_id: runId.value || undefined,
    }),
    { watch: [status, runId] },
  )
}
