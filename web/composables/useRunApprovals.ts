import type { Approval } from '~/types/api'
import { hydrateApprovalsFromEvent, type ApprovalSseEvent } from '~/utils/approvalStream'

export interface RunApprovalsState {
  pending: Approval[]
  current: Approval | null
  stage: string | null
}

export function useRunApprovals() {
  const pending = ref<Approval[]>([])
  const stage = ref<string | null>(null)
  const loading = ref(false)
  const ssePendingCount = ref(0)

  const current = computed(() => pending.value[0] ?? null)
  const pendingCount = computed(() =>
    pending.value.length > 0 ? pending.value.length : ssePendingCount.value,
  )
  const awaitingApprovals = computed(
    () => stage.value === 'executor_approvals' && pendingCount.value > 0,
  )

  function seedFromSseEvent(evt: ApprovalSseEvent) {
    const hydrated = hydrateApprovalsFromEvent(evt)
    if (!hydrated) return
    stage.value = hydrated.stage
    ssePendingCount.value = hydrated.pendingCount
    if (hydrated.pending.length > 0) {
      pending.value = hydrated.pending
    }
  }

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
      ssePendingCount.value = pending.value.length
    }
    catch {
      pending.value = []
      ssePendingCount.value = 0
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
    ssePendingCount.value = 0
  }

  return {
    pending,
    current,
    pendingCount,
    awaitingApprovals,
    stage,
    loading,
    fetchPending,
    seedFromSseEvent,
    shiftQueue,
    clear,
  }
}
