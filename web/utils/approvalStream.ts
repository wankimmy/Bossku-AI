import type { Approval } from '~/types/api'

export type ApprovalSseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
  stage?: string
  artifacts?: Record<string, unknown>
}

export const APPROVAL_TERMINAL_AFTER = new Set([
  'approval_feedback_received',
  'run_completed',
  'run_failed',
  'planner_failed',
])

const NON_CLARIFICATION_PAUSE_TYPES = new Set([
  'approval_requested',
  'approval_feedback_received',
])

export function isApprovalPauseEvent(evt: ApprovalSseEvent | undefined): boolean {
  if (!evt) return false
  const type = String(evt.type ?? '')
  if (type === 'approval_requested' || type === 'run_paused') return true
  if (String(evt.stage ?? '') === 'executor_approvals') return true
  return false
}

export function lastApprovalEventIndex(events: ApprovalSseEvent[]): number {
  for (let i = events.length - 1; i >= 0; i--) {
    if (isApprovalPauseEvent(events[i])) {
      return i
    }
  }
  return -1
}

/** True when executor change approvals are pending (not superseded by resume/terminal events). */
export function isAwaitingApprovals(events: ApprovalSseEvent[]): boolean {
  const idx = lastApprovalEventIndex(events)
  if (idx === -1) {
    return false
  }
  for (let i = idx + 1; i < events.length; i++) {
    const type = String(events[i]?.type ?? '')
    if (APPROVAL_TERMINAL_AFTER.has(type)) {
      return false
    }
  }
  return true
}

function parseApprovalFromUnknown(raw: unknown): Approval | null {
  if (raw === null || typeof raw !== 'object') return null
  const o = raw as Record<string, unknown>
  const id = String(o.id ?? '')
  if (!id) return null
  return {
    id,
    run_id: o.run_id != null ? String(o.run_id) : undefined,
    operation_type: String(o.operation_type ?? 'file_write'),
    description: o.description != null ? String(o.description) : undefined,
    risk_level: o.risk_level != null ? String(o.risk_level) : undefined,
    evidence: (o.evidence != null && typeof o.evidence === 'object'
      ? o.evidence as Record<string, unknown>
      : undefined),
    status: String(o.status ?? 'pending'),
    decision_note: o.decision_note != null ? String(o.decision_note) : undefined,
    created_at: o.created_at != null ? String(o.created_at) : undefined,
  }
}

/** Seed pending queue from approval_requested SSE artifacts. */
export function hydrateApprovalsFromEvent(evt: ApprovalSseEvent): {
  stage: string
  pending: Approval[]
  pendingCount: number
} | null {
  if (!isApprovalPauseEvent(evt)) return null

  const artifacts = evt.artifacts
  if (artifacts === null || typeof artifacts !== 'object') {
    return { stage: 'executor_approvals', pending: [], pendingCount: 0 }
  }

  const art = artifacts as Record<string, unknown>
  const current = parseApprovalFromUnknown(art.current_approval)
  const pendingCount = typeof art.pending_count === 'number'
    ? art.pending_count
    : (Array.isArray(art.approval_ids) ? art.approval_ids.length : (current ? 1 : 0))

  const pending = current ? [current] : []

  return {
    stage: 'executor_approvals',
    pending,
    pendingCount: Math.max(pendingCount, pending.length),
  }
}

export function isApprovalRelatedPauseType(type: string): boolean {
  return NON_CLARIFICATION_PAUSE_TYPES.has(type)
}
