import { describe, expect, it } from 'vitest'
import type { SseEvent } from '~/composables/useRunStream'
import {
  applyBosskuEventsToPixelOffice,
  createPixelOfficeAdapterState,
  resetPixelOfficeAdapterState,
} from '~/utils/pixelOfficeAdapter'
import { BOSSKU_AGENT_IDS } from '~/utils/pixelOfficeLayout'

function evt(partial: SseEvent): SseEvent {
  return { seq: 1, ...partial }
}

describe('pixelOfficeAdapter', () => {
  it('spawns cast on run_started', () => {
    const state = createPixelOfficeAdapterState()
    const messages = applyBosskuEventsToPixelOffice(
      [evt({ type: 'run_started', seq: 1, id: 'e1' })],
      state,
    )
    expect(messages.some(m => m.type === 'existingAgents')).toBe(true)
    expect(messages.filter(m => m.type === 'agentCreated').length).toBe(0)
    expect(state.castSpawned).toBe(true)
  })

  it('activates executor on executor_step_started', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice(
      [evt({ type: 'run_started', seq: 1, id: 'e1' })],
      state,
    )
    const messages = applyBosskuEventsToPixelOffice(
      [
        evt({
          type: 'executor_step_started',
          seq: 2,
          id: 'e2',
          agent: 'executor',
          message: 'Running step',
        }),
      ],
      state,
    )
    expect(messages.some(m => m.type === 'agentStatus' && m.id === BOSSKU_AGENT_IDS.executor && m.status === 'active')).toBe(true)
    expect(messages.some(m => m.type === 'agentToolStart' && m.id === BOSSKU_AGENT_IDS.executor)).toBe(true)
  })

  it('maps tool_call to tools agent with reading label', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice([evt({ type: 'run_started', seq: 1, id: 'e1' })], state)
    const messages = applyBosskuEventsToPixelOffice(
      [
        evt({
          type: 'tool_call',
          seq: 3,
          id: 'e3',
          tool: 'file_read_safe',
          payload: { path: 'README.md' },
          status: 'ok',
        }),
      ],
      state,
    )
    const start = messages.find(m => m.type === 'agentToolStart' && m.id === BOSSKU_AGENT_IDS.tools)
    expect(start).toBeTruthy()
    expect(String(start?.status)).toContain('Reading')
  })

  it('shows waiting bubble on clarification_requested', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice([evt({ type: 'run_started', seq: 1, id: 'e1' })], state)
    const messages = applyBosskuEventsToPixelOffice(
      [evt({ type: 'clarification_requested', seq: 4, id: 'e4' })],
      state,
    )
    expect(
      messages.some(
        m => m.type === 'agentStatus' && m.id === BOSSKU_AGENT_IDS.orchestrator && m.status === 'waiting',
      ),
    ).toBe(true)
  })

  it('dedupes events by id', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice([evt({ type: 'run_started', seq: 1, id: 'e1' })], state)
    const first = applyBosskuEventsToPixelOffice(
      [evt({ type: 'planner_started', seq: 2, id: 'dup' })],
      state,
    )
    const second = applyBosskuEventsToPixelOffice(
      [evt({ type: 'planner_started', seq: 2, id: 'dup' })],
      state,
    )
    expect(first.length).toBeGreaterThan(0)
    expect(second.length).toBe(0)
  })

  it('resets state', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice([evt({ type: 'run_started', seq: 1, id: 'e1' })], state)
    resetPixelOfficeAdapterState(state)
    expect(state.castSpawned).toBe(false)
    expect(state.processedIds.size).toBe(0)
  })
})
