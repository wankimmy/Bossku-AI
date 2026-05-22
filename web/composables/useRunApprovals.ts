import type { Approval } from '~/types/api'

export interface RunApprovalsState {
  pending: Approval[]
  current: Approval | null
  stage: string | null
}

export function useRunApprovals() {
  const pending = ref<Approval[]>([])
  const stage = ref<string | null>(null)
  const loading = ref(false)

  const current = computed(() => pending.value[0] ?? null)
  const pendingCount = computed(() => pending.value.length)
  const awaitingApprovals = computed(
    () => stage.value === 'executor_approvals' && pendingCount.value > 0,
  )

  async function fetchPending(runId: string) {
    if (!runId) return
    loading.value = true
    try {
      const api = useApi()
      const data = await api.get<{
        stage?: string
        pending?: Approval[]
      }>(`/runs/${runId}/approvals`)
      stage.value = data.stage ?? null
      pending.value = Array.isArray(data.pending) ? data.pending : []
    }
    catch {
      pending.value = []
    }
    finally {
      loading.value = false
    }
  }

  function shiftQueue() {
    pending.value = pending.value.slice(1)
  }

  function clear() {
    pending.value = []
    stage.value = null
  }

  return {
    pending,
    current,
    pendingCount,
    awaitingApprovals,
    stage,
    loading,
    fetchPending,
    shiftQueue,
    clear,
  }
}
