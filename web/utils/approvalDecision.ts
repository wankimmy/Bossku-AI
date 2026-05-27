import type { FetchError } from 'ofetch'

type ApprovalApiResponse = {
  already_decided?: boolean
  run_has_pending?: boolean
  message?: string
}

export type ApprovalDecisionPayload = {
  runHasPending: boolean
  note: string
}

function extractMessage(err: unknown): string {
  if (err && typeof err === 'object' && 'data' in err) {
    const data = (err as FetchError).data
    if (data && typeof data === 'object' && 'message' in data) {
      return String((data as { message?: string }).message ?? '')
    }
  }

  return ''
}

/** True when approve/reject can advance the UI queue despite a non-success response. */
export function isIdempotentApprovalOutcome(err: unknown, action: 'approve' | 'reject'): boolean {
  if (!err || typeof err !== 'object') {
    return false
  }

  const status = 'status' in err ? Number((err as { status?: number }).status) : 0
  const message = extractMessage(err).toLowerCase()

  if (status === 422) {
    if (action === 'approve' && message.includes('not pending')) {
      return true
    }
    if (action === 'reject' && message.includes('not pending')) {
      return true
    }
  }

  // Approval was recorded but apply failed on the server — status was set to rejected.
  // We can still advance the queue so the run can continue and the orchestrator handles the failure.
  if (action === 'approve' && status === 500 && message.includes('approved but apply failed')) {
    return true
  }

  return false
}

export function isAlreadyDecidedResponse(data: unknown): boolean {
  return Boolean(data && typeof data === 'object' && (data as ApprovalApiResponse).already_decided === true)
}

/** Parse approve/reject API body; default runHasPending true when unknown (do not resume early). */
export function runHasPendingFromDecisionResponse(data: unknown): boolean {
  if (!data || typeof data !== 'object') {
    return true
  }

  // FetchError: parsed response body lives in .data, not on the error object itself
  const body = ('status' in data && 'data' in data && data.data && typeof data.data === 'object')
    ? (data as { data: unknown }).data
    : data

  const flag = (body as ApprovalApiResponse).run_has_pending
  if (flag === false) {
    return false
  }

  return true
}
