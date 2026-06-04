import { describe, expect, it } from 'vitest'
import {
  buildFreeTextClarificationAnswers,
  resolveSubmitTarget,
  type SubmitRoutingContext,
} from '../utils/resolveSubmitTarget'

function baseCtx(overrides: Partial<SubmitRoutingContext> = {}): SubmitRoutingContext {
  return {
    running: false,
    submittingClarification: false,
    uploadingAttachments: false,
    showClarificationModal: false,
    showApprovalModal: false,
    isLocalCommandsClarification: false,
    awaitingClarification: false,
    runStatus: 'idle',
    hasClarificationRequest: false,
    promptTrimmed: 'start with stage 1 first',
    ...overrides,
  }
}

describe('resolveSubmitTarget', () => {
  it('routes to answer when run awaits input and user typed a reply', () => {
    expect(resolveSubmitTarget(baseCtx({
      awaitingClarification: true,
      hasClarificationRequest: true,
      runStatus: 'awaiting_input',
    }))).toBe('answer')
  })

  it('routes to answer when awaiting input even if clarification modal is open', () => {
    expect(resolveSubmitTarget(baseCtx({
      showClarificationModal: true,
      awaitingClarification: true,
      hasClarificationRequest: true,
      runStatus: 'awaiting_input',
    }))).toBe('answer')
  })

  it('routes to new_run when not awaiting input', () => {
    expect(resolveSubmitTarget(baseCtx({
      promptTrimmed: 'build a new feature',
    }))).toBe('new_run')
  })

  it('routes to new_run when awaiting but prompt is empty', () => {
    expect(resolveSubmitTarget(baseCtx({
      awaitingClarification: true,
      hasClarificationRequest: true,
      promptTrimmed: '',
    }))).toBe('new_run')
  })

  it('routes to new_run when approvals modal is open', () => {
    expect(resolveSubmitTarget(baseCtx({
      showApprovalModal: true,
      awaitingClarification: true,
      hasClarificationRequest: true,
    }))).toBe('new_run')
  })
})

describe('buildFreeTextClarificationAnswers', () => {
  it('wraps composer text as a single free-text answer', () => {
    expect(buildFreeTextClarificationAnswers('q1', 'start with stage 1 first')).toEqual([
      { question_id: 'q1', free_text: 'start with stage 1 first' },
    ])
  })
})
