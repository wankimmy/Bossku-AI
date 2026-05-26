import type { FetchError } from 'ofetch'

type ApprovalApiResponse = {
  already_decided?: boolean
  message?: string
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

  return false
}

export function isAlreadyDecidedResponse(data: unknown): boolean {
  return Boolean(data && typeof data === 'object' && (data as ApprovalApiResponse).already_decided === true)
}
