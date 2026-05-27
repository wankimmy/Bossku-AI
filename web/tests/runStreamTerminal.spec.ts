import { describe, expect, it } from 'vitest'
import { isRunStatusTerminal, isTerminalStreamEvent } from '../utils/runStreamTerminal'

describe('runStreamTerminal', () => {
  it('detects terminal stream event types', () => {
    expect(isTerminalStreamEvent({ type: 'run_completed' })).toBe(true)
    expect(isTerminalStreamEvent({ type: 'planner_done' })).toBe(false)
  })

  it('detects terminal run status from API', () => {
    expect(isRunStatusTerminal('completed')).toBe(true)
    expect(isRunStatusTerminal('running')).toBe(false)
  })
})
