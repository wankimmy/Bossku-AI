import { computed, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import type { Approval } from '~/types/api'
import { hydrateApprovalsFromEvent, type ApprovalSseEvent } from '../utils/approvalStream'

export interface RunApprovalsState {
  pending: Approval[]
  current: Approval | null
  stage: string | null
}

function onlyPending(approvals: Approval[]): Approval[] {
  return approvals.filter((a) => a.status === 'pending')
}

export type FetchPendingResult = {
  ok: boolean
  pending: Approval[]
  error?: string
}

export function useRunApprovals() {
  const pending = ref<Approval[]>([])
  const stage = ref<string | null>(null)
  const loading = ref(false)
  const fetchError = ref<string | null>(null)
  const ssePendingCount = ref(0)

  const current = computed(() => pending.value[0] ?? null)
  const pendingCount = computed(() => {
    if (pending.value.length > 0) {
      return pending.value.length
    }
    if (stage.value === 'executor_approvals' && ssePendingCount.value > 0 && !loading.value) {
      return ssePendingCount.value
    }

    return pending.value.length
  })
  const awaitingApprovals = computed(
    () => stage.value === 'executor_approvals' && pendingCount.value > 0,
  )

  function seedFromSseEvent(evt: ApprovalSseEvent) {
    const hydrated = hydrateApprovalsFromEvent(evt)
    if (!hydrated) return
    stage.value = hydrated.stage
    ssePendingCount.value = hydrated.pendingCount
    const seeded = onlyPending(hydrated.pending)
    if (seeded.length > 0) {
      pending.value = seeded
    }
  }

  async function fetchPending(runId: string): Promise<FetchPendingResult> {
    if (!runId) {
      return { ok: false, pending: pending.value, error: 'Missing run id' }
    }
    loading.value = true
    fetchError.value = null
    try {
      const api = useApi()
      const data = await api.get<{
        stage?: string
        pending?: Approval[]
      }>(`/runs/${runId}/approvals`)
      stage.value = data.stage ?? null
      pending.value = onlyPending(Array.isArray(data.pending) ? data.pending : [])
      ssePendingCount.value = pending.value.length

      return { ok: true, pending: pending.value }
    }
    catch (e: unknown) {
      const message = e instanceof Error ? e.message : 'Failed to load approval queue'
      fetchError.value = message

      return { ok: false, pending: pending.value, error: message }
    }
    finally {
      loading.value = false
    }
  }

  function shiftQueue() {
    pending.value = pending.value.slice(1)
    ssePendingCount.value = pending.value.length
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
    fetchError,
    fetchPending,
    seedFromSseEvent,
    shiftQueue,
    clear,
  }
}
