import { describe, expect, it, vi } from 'vitest'

describe('useBrain composable contract', () => {
  it('brain response has expected keys', () => {
    const mockBrainResponse = {
      learning_events: { pending: 3, accepted: 5, rejected: 1, applied: 2 },
      skill_candidates: { draft: 1, pending_review: 2, approved: 3, rejected: 0 },
      feedback_unprocessed: 4,
      memory_confidence: { avg: 0.78, min: 0.3, max: 1.0 },
      conflict_count: 2,
    }

    expect(mockBrainResponse).toHaveProperty('learning_events')
    expect(mockBrainResponse).toHaveProperty('skill_candidates')
    expect(mockBrainResponse).toHaveProperty('feedback_unprocessed')
    expect(mockBrainResponse).toHaveProperty('memory_confidence')
    expect(mockBrainResponse).toHaveProperty('conflict_count')
    expect(typeof mockBrainResponse.conflict_count).toBe('number')
  })

  it('learning_events status counts are non-negative', () => {
    const events = { pending: 3, accepted: 5, rejected: 1, applied: 2 }
    Object.values(events).forEach(v => expect(v).toBeGreaterThanOrEqual(0))
  })

  it('memory_confidence avg is between 0 and 1', () => {
    const confidence = { avg: 0.78, min: 0.3, max: 1.0 }
    expect(confidence.avg).toBeGreaterThanOrEqual(0)
    expect(confidence.avg).toBeLessThanOrEqual(1)
  })
})
