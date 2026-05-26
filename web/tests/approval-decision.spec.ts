import { describe, expect, it } from 'vitest'
import { runHasPendingFromDecisionResponse } from '../utils/approvalDecision'

describe('runHasPendingFromDecisionResponse', () => {
  it('returns true when response is missing', () => {
    expect(runHasPendingFromDecisionResponse(null)).toBe(true)
  })

  it('returns false only when run_has_pending is explicitly false', () => {
    expect(runHasPendingFromDecisionResponse({ run_has_pending: false })).toBe(false)
  })

  it('returns true when run_has_pending is true', () => {
    expect(runHasPendingFromDecisionResponse({ run_has_pending: true })).toBe(true)
  })

  it('returns true when run_has_pending is omitted', () => {
    expect(runHasPendingFromDecisionResponse({ message: 'ok' })).toBe(true)
  })
})
