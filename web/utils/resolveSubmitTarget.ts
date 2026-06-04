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
}

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

export function buildFreeTextClarificationAnswers(
  questionId: string,
  freeText: string,
): Array<{ question_id: string; free_text: string }> {
  return [{ question_id: questionId, free_text: freeText }]
}
