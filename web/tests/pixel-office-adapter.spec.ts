import { describe, expect, it } from 'vitest'
import type { SseEvent } from '~/composables/useRunStream'
import {
  applyBosskuEventsToPixelOffice,
  createPixelOfficeAdapterState,
  resetPixelOfficeAdapterState,
  spawnCastMessages,
} from '~/utils/pixelOfficeAdapter'
import { BOSSKU_AGENT_IDS, specialistAgentId } from '~/utils/pixelOfficeLayout'

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

    const cast = messages.find(m => m.type === 'existingAgents')
    const meta = cast?.agentMeta as Record<number, { label: string }>
    expect(meta[BOSSKU_AGENT_IDS.executor].label).toBe('Executor')
    expect(meta[BOSSKU_AGENT_IDS.orchestrator].label).toBe('Orchestrator')
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

  it('spawns and activates dynamic specialist agents from run events', () => {
    const state = createPixelOfficeAdapterState()
    applyBosskuEventsToPixelOffice([evt({ type: 'run_started', seq: 1, id: 'e1' })], state)

    const specialist = {
      id: '11111111-1111-4111-8111-111111111111',
      role_slug: 'checkout-specialist',
      display_name: 'Checkout Specialist',
      pixel_palette: 3,
      pixel_hue_shift: 15,
      seat_id: 'f-specialist',
    }

    const selected = applyBosskuEventsToPixelOffice([
      evt({
        type: 'specialist_agent_selected',
        seq: 2,
        id: 'e2',
        agent: 'checkout-specialist',
        artifacts: { specialist_agent: specialist },
      }),
    ], state)
    const expectedId = specialistAgentId(specialist.id)
    const cast = selected.find(m => m.type === 'existingAgents')
    expect(cast).toBeTruthy()
    expect((cast?.agents as number[])).toContain(expectedId)
    expect((cast?.agentMeta as Record<number, { palette: number; label: string }>)[expectedId].palette).toBe(3)
    expect((cast?.agentMeta as Record<number, { palette: number; label: string }>)[expectedId].label).toBe('Checkout Specialist')

    const started = applyBosskuEventsToPixelOffice([
      evt({
        type: 'specialist_agent_started',
        seq: 3,
        id: 'e3',
        agent: 'checkout-specialist',
        summary: 'Specialist is preparing handoff.',
        artifacts: { specialist_agent: specialist },
      }),
    ], state)
    expect(started.some(m => m.type === 'agentStatus' && m.id === expectedId && m.status === 'active')).toBe(true)
    expect(started.some(m => m.type === 'agentToolStart' && m.id === expectedId)).toBe(true)
  })

  it('spawnCastMessages includes role labels for dynamic specialists', () => {
    const specialistId = specialistAgentId('seo-writer')
    const messages = spawnCastMessages([
      {
        role: 'seo-writer',
        id: specialistId,
        meta: {
          palette: 2,
          hueShift: 0,
          seatId: null,
          label: 'SEO Writer',
        },
      },
    ])
    const cast = messages.find(m => m.type === 'existingAgents')
    const meta = cast?.agentMeta as Record<number, { label: string }>
    expect(meta[specialistId].label).toBe('SEO Writer')
  })
})
