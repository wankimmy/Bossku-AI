import { describe, expect, it } from 'vitest'
import { useRunApprovals } from '../composables/useRunApprovals'

describe('useRunApprovals', () => {
  it('shiftQueue clears stale pending count when queue empties', () => {
    const state = useRunApprovals()
    state.stage.value = 'executor_approvals'
    state.pending.value = [{
      id: 'a1',
      operation_type: 'file_write',
      status: 'pending',
    }]
    state.seedFromSseEvent({
      type: 'approval_requested',
      artifacts: { pending_count: 3, current_approval: state.pending.value[0] },
    })

    state.shiftQueue()

    expect(state.pending.value).toHaveLength(0)
    expect(state.pendingCount.value).toBe(0)
    expect(state.awaitingApprovals.value).toBe(false)
  })

  it('pendingCount follows loaded queue length not sse hint', () => {
    const state = useRunApprovals()
    state.stage.value = 'executor_approvals'
    state.pending.value = [
      { id: 'a1', operation_type: 'file_write', status: 'pending' },
      { id: 'a2', operation_type: 'file_write', status: 'pending' },
    ]
    state.seedFromSseEvent({
      type: 'approval_requested',
      artifacts: { pending_count: 5 },
    })

    expect(state.pendingCount.value).toBe(2)
  })
})
