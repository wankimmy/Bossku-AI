import { describe, expect, it } from 'vitest'
import {
  hydrateApprovalsFromEvent,
  isAwaitingApprovals,
} from '../utils/approvalStream'
import { isAwaitingClarification } from '../utils/clarificationStream'

describe('approval stream handling', () => {
  it('recognizes approval_requested as approval pause', () => {
    const events = [
      { type: 'executor_done' },
      {
        type: 'approval_requested',
        run_id: 'run-1',
        status: 'awaiting_input',
        to_agent: 'user',
        summary: '2 change(s) need your approval',
        artifacts: {
          pending_count: 2,
          approval_ids: ['a1', 'a2'],
          current_approval: {
            id: 'a1',
            operation_type: 'file_write',
            description: 'Modify file: foo.ts',
            status: 'pending',
          },
        },
      },
    ]
    expect(isAwaitingApprovals(events)).toBe(true)
    expect(isAwaitingClarification(events)).toBe(false)
  })

  it('does not treat approval_requested as clarification pause', () => {
    const events = [{
      type: 'approval_requested',
      run_id: 'run-1',
      status: 'awaiting_input',
      to_agent: 'user',
      summary: '1 change(s) need your approval before the run can continue.',
    }]
    expect(isAwaitingClarification(events)).toBe(false)
  })

  it('clears awaiting approvals after approval_feedback_received', () => {
    const events = [
      { type: 'approval_requested', run_id: 'run-1', status: 'awaiting_input' },
      { type: 'approval_feedback_received' },
    ]
    expect(isAwaitingApprovals(events)).toBe(false)
  })

  it('hydrates current approval from SSE artifacts', () => {
    const seeded = hydrateApprovalsFromEvent({
      type: 'approval_requested',
      artifacts: {
        pending_count: 3,
        current_approval: {
          id: 'ap-1',
          operation_type: 'terminal_command',
          description: 'Run command: npm test',
          status: 'pending',
          evidence: { command: 'npm test' },
        },
      },
    })
    expect(seeded?.stage).toBe('executor_approvals')
    expect(seeded?.pendingCount).toBe(3)
    expect(seeded?.pending[0]?.id).toBe('ap-1')
  })
})
