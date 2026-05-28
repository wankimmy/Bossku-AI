import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useRunApprovals } from '../composables/useRunApprovals'

const get = vi.fn()

vi.mock('~/composables/useApi', () => ({
  useApi: () => ({ get }),
}))

describe('useRunApprovals', () => {
  beforeEach(() => {
    get.mockReset()
  })
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

  it('fetchPending clears queue on error to avoid stale approval ids', async () => {
    const state = useRunApprovals()
    state.pending.value = [
      { id: 'a1', operation_type: 'file_write', status: 'pending' },
      { id: 'a2', operation_type: 'file_write', status: 'pending' },
    ]
    get.mockRejectedValueOnce(new Error('network'))

    const result = await state.fetchPending('run-1')

    expect(result.ok).toBe(false)
    expect(result.pending).toHaveLength(0)
    expect(state.pending.value).toHaveLength(0)
    expect(state.fetchError.value).toBe('network')
  })

  it('fetchPending replaces queue on success', async () => {
    const state = useRunApprovals()
    state.pending.value = [{ id: 'stale', operation_type: 'file_write', status: 'pending' }]
    get.mockResolvedValueOnce({
      stage: 'executor_approvals',
      pending: [{ id: 'fresh', operation_type: 'file_write', status: 'pending' }],
    })

    const result = await state.fetchPending('run-1')

    expect(result.ok).toBe(true)
    expect(result.pending).toHaveLength(1)
    expect(state.pending.value[0]?.id).toBe('fresh')
  })
})
