import { describe, expect, it, beforeEach } from 'vitest'
import {
  clearActiveRunBinding,
  loadActiveRunBinding,
  saveActiveRunBinding,
  updateActiveRunLastSeq,
} from '../utils/activeRunStorage'

describe('activeRunStorage', () => {
  beforeEach(() => {
    sessionStorage.clear()
    clearActiveRunBinding()
  })

  it('round-trips binding through sessionStorage', () => {
    saveActiveRunBinding({
      convId: 'conv_1',
      runId: 'run_1',
      lastSeq: 3,
    })
    expect(loadActiveRunBinding()).toEqual({
      convId: 'conv_1',
      runId: 'run_1',
      lastSeq: 3,
    })
  })

  it('updateActiveRunLastSeq patches lastSeq only', () => {
    saveActiveRunBinding({
      convId: 'conv_1',
      runId: 'run_1',
      lastSeq: 1,
    })
    updateActiveRunLastSeq(5)
    expect(loadActiveRunBinding()?.lastSeq).toBe(5)
  })

  it('clearActiveRunBinding removes stored value', () => {
    saveActiveRunBinding({
      convId: 'conv_1',
      runId: 'run_1',
      lastSeq: 0,
    })
    clearActiveRunBinding()
    expect(loadActiveRunBinding()).toBeNull()
  })
})
