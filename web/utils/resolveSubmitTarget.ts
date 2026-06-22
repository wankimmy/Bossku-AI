export type SubmitTarget = 'answer' | 'new_run'

export type SubmitRoutingContext = {
  running: boolean
  submittingClarification: boolean
  uploadingAttachments: boolean
  showClarificationModal: boolean
  showApprovalModal: boolean
  isLocalCommandsClarification: boolean
  awaitingClarification: boolean
  runStatus: string | null | undefined
  hasClarificationRequest: boolean
  promptTrimmed: string
  completedRunId?: string | null
}

const CONTINUATION_RE = /\b(proceed|continue|go ahead|ok read|read it|do it|yes|execute it)\b/i

/**
 * Decide whether the composer should resume a paused run or start a new one.
 */
export function resolveSubmitTarget(ctx: SubmitRoutingContext): SubmitTarget {
  if (ctx.running || ctx.submittingClarification || ctx.uploadingAttachments) {
    return 'new_run'
  }

  if (ctx.showApprovalModal || ctx.isLocalCommandsClarification) {
    return 'new_run'
  }

  const awaitingInput = ctx.awaitingClarification
    || String(ctx.runStatus ?? '').toLowerCase() === 'awaiting_input'

  if (awaitingInput && ctx.promptTrimmed !== '') {
    return 'answer'
  }

  return 'new_run'
}

/** Completed-run follow-ups should start a contextual new run, not resume approvals. */
export function shouldAttachContinuationRunId(ctx: SubmitRoutingContext): boolean {
  if (ctx.awaitingClarification || String(ctx.runStatus ?? '').toLowerCase() === 'awaiting_input') {
    return false
  }

  const status = String(ctx.runStatus ?? '').toLowerCase()
  if (status !== 'completed') {
    return false
  }

  return CONTINUATION_RE.test(ctx.promptTrimmed) && Boolean(ctx.completedRunId)
}

export function buildFreeTextClarificationAnswers(
  questionId: string,
  freeText: string,
): Array<{ question_id: string; free_text: string }> {
  return [{ question_id: questionId, free_text: freeText }]
}
