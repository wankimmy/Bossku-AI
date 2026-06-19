import type { SseEvent } from '~/composables/useRunStream'
import {
  BOSSKU_AGENT_IDS,
  BOSSKU_PIXEL_AGENT_ROLES,
  agentIdForRole,
  loadPersistedSeats,
  specialistAgentMeta,
  type BosskuPixelAgentRole,
  type SpecialistPixelAgentInput,
} from '~/utils/pixelOfficeLayout'
import { formatToolCallSummary, formatToolCallTitle, isToolCallEvent } from '~/utils/formatToolCall'

export type PixelOfficeHostMessage = Record<string, unknown> & { type: string }

const READ_TOOLS = new Set(['file_read_safe', 'file_search', 'file_glob'])
const WRITE_TOOLS = new Set(['file_write_proposed'])

let toolSeq = 0

function nextToolId(): string {
  toolSeq += 1
  return `bossku-tool-${toolSeq}`
}

function inferAgent(evt: SseEvent): string {
  if (isToolCallEvent(evt)) return 'tools'
  const type = String(evt.type ?? '')
  const eventAgent = String(evt.agent ?? '')
  if (type.includes('specialist_agent')) {
    const payload = specialistPayloadFromEvent(evt)
    return eventAgent || String(payload?.role_slug ?? 'specialist-agent')
  }
  if (type.includes('planner')) return 'orchestrator'
  if (type.includes('executor')) return 'executor'
  if (type.includes('security')) return 'security-auditor'
  if (type.includes('auditor')) return 'auditor'
  if (type.includes('eval')) return 'evaluator'
  if (type.includes('final')) return 'final-reviewer'
  if (type.includes('router')) return 'router'
  if (type.includes('memory')) return 'memory'
  const agent = eventAgent
  if (agent && agentIdForRole(agent)) return agent
  return 'orchestrator'
}

function isStageStart(type: string): boolean {
  return (
    type.endsWith('_started')
    || type === 'run_started'
    || type === 'planner_started'
    || type === 'executor_step_started'
    || type === 'executor_revision_started'
    || type === 'executor_code_review_started'
    || type === 'auditor_started'
    || type === 'security_auditor_started'
    || type === 'final_reviewer_started'
    || type === 'post_memory_eval_started'
    || type === 'memory_retrieved'
    || type === 'commands_executed'
  )
}

function isStageDone(type: string): boolean {
  return (
    type.endsWith('_done')
    || type.endsWith('_finished')
    || type === 'run_completed'
    || type === 'planner_done'
    || type === 'executor_step_done'
    || type === 'executor_revision_done'
    || type === 'auditor_done'
    || type === 'security_auditor_done'
    || type === 'final_reviewer_done'
    || type === 'files_applied'
    || type === 'files_apply_skipped'
    || type === 'commands_executed'
  )
}

function toolStatusLabel(evt: SseEvent): string {
  const tool = String(evt.tool ?? '')
  const title = formatToolCallTitle(evt)
  const summary = formatToolCallSummary(evt)
  if (READ_TOOLS.has(tool)) return `Reading: ${summary}`
  if (WRITE_TOOLS.has(tool)) return `Writing: ${summary}`
  return `${title}: ${summary}`
}

type DynamicPixelAgent = {
  role: string
  id: number
  meta: {
    palette: number
    hueShift: number
    seatId: string | null
    label: string
  }
}

function dynamicIds(state: PixelOfficeAdapterState): number[] {
  return Object.values(state.dynamicAgents).map(agent => agent.id)
}

function allKnownAgentIds(state?: PixelOfficeAdapterState): number[] {
  return [
    ...BOSSKU_PIXEL_AGENT_ROLES.map(role => BOSSKU_AGENT_IDS[role]),
    ...(state ? dynamicIds(state) : []),
  ]
}

function setActiveById(activeId: number, status: string, state?: PixelOfficeAdapterState): PixelOfficeHostMessage[] {
  const messages: PixelOfficeHostMessage[] = []
  for (const id of allKnownAgentIds(state)) {
    if (id === activeId) {
      messages.push({ type: 'agentStatus', id, status: 'active' })
    } else {
      messages.push({ type: 'agentStatus', id, status: 'waiting' })
      messages.push({ type: 'agentToolsClear', id })
    }
  }
  if (status) {
    const toolId = nextToolId()
    messages.push({
      type: 'agentToolStart',
      id: activeId,
      toolId,
      status,
    })
  }
  return messages
}

function setActiveMessages(activeRole: BosskuPixelAgentRole, status: string, state?: PixelOfficeAdapterState): PixelOfficeHostMessage[] {
  return setActiveById(BOSSKU_AGENT_IDS[activeRole], status, state)
}

function clearAllTools(state?: PixelOfficeAdapterState): PixelOfficeHostMessage[] {
  return allKnownAgentIds(state).flatMap(id => [
    { type: 'agentToolsClear', id },
    { type: 'agentToolPermissionClear', id },
  ])
}

export function spawnCastMessages(dynamicAgents: DynamicPixelAgent[] = []): PixelOfficeHostMessage[] {
  const meta = loadPersistedSeats()
  const agents = [
    ...BOSSKU_PIXEL_AGENT_ROLES.map(role => BOSSKU_AGENT_IDS[role]),
    ...dynamicAgents.map(agent => agent.id),
  ]
  const agentMeta: Record<number, { palette: number; hueShift: number; seatId: string | null; label: string }> = {}
  for (const role of BOSSKU_PIXEL_AGENT_ROLES) {
    const id = BOSSKU_AGENT_IDS[role]
    const m = meta[id]
    agentMeta[id] = { palette: m.palette, hueShift: m.hueShift, seatId: m.seatId, label: m.label }
  }
  for (const agent of dynamicAgents) {
    const persisted = meta[agent.id]
    const m = persisted ? { ...agent.meta, ...persisted, label: agent.meta.label } : agent.meta
    agentMeta[agent.id] = { palette: m.palette, hueShift: m.hueShift, seatId: m.seatId, label: m.label }
  }
  return [{ type: 'existingAgents', agents, agentMeta }]
}

function mapSingleEvent(evt: SseEvent, state: PixelOfficeAdapterState): PixelOfficeHostMessage[] {
  const type = String(evt.type ?? '')
  const agentRole = inferAgent(evt)
  const agentId = agentIdForRole(agentRole) ?? state.dynamicAgents[agentRole]?.id ?? specialistDescriptorFromEvent(evt)?.id

  if (type === 'run_started') {
    toolSeq = 0
    return spawnCastMessages(Object.values(state.dynamicAgents))
  }

  if (type === 'specialist_agent_selected') {
    return state.castSpawned ? spawnCastMessages(Object.values(state.dynamicAgents)) : []
  }

  if (!state.castSpawned) return []

  if (type === 'clarification_requested') {
    const stage = String(evt.stage ?? '')
    const id = stage === 'user_local_commands' ? BOSSKU_AGENT_IDS.executor : BOSSKU_AGENT_IDS.orchestrator
    return [
      ...clearAllTools(state),
      { type: 'agentStatus', id, status: 'waiting' },
    ]
  }

  if (type === 'approval_requested') {
    const id = BOSSKU_AGENT_IDS.executor
    return [
      ...clearAllTools(state),
      { type: 'agentToolPermission', id },
      { type: 'agentStatus', id, status: 'active' },
    ]
  }

  if (type === 'approval_feedback_received') {
    return [{ type: 'agentToolPermissionClear', id: BOSSKU_AGENT_IDS.executor }]
  }

  if (type === 'clarification_received') {
    return [{ type: 'agentStatus', id: BOSSKU_AGENT_IDS.orchestrator, status: 'active' }]
  }

  if (type === 'run_completed' || type === 'run_failed' || type === 'planner_failed') {
    return [
      ...clearAllTools(state),
      ...allKnownAgentIds(state).map(id => ({
        type: 'agentStatus',
        id,
        status: 'waiting',
      })),
    ]
  }

  if (isToolCallEvent(evt) && agentId) {
    const toolId = nextToolId()
    const status = toolStatusLabel(evt)
    return [
      { type: 'agentStatus', id: agentId, status: 'active' },
      { type: 'agentToolStart', id: agentId, toolId, status },
      ...allKnownAgentIds(state).filter(id => id !== agentId).map(id => ({
        type: 'agentStatus',
        id,
        status: 'waiting',
      })),
    ]
  }

  if (isStageStart(type) && agentId) {
    const summary = String(evt.summary ?? evt.message ?? type.replaceAll('_', ' '))
    const role = agentRole as BosskuPixelAgentRole
    if (agentIdForRole(role)) {
      return setActiveMessages(role, summary, state)
    }
    return setActiveById(agentId, summary, state)
  }

  if (isStageDone(type) && agentId) {
    return [
      { type: 'agentToolsClear', id: agentId },
      { type: 'agentToolPermissionClear', id: agentId },
    ]
  }

  return []
}

export type PixelOfficeAdapterState = {
  lastSeq: number
  castSpawned: boolean
  processedIds: Set<string>
  dynamicAgents: Record<string, DynamicPixelAgent>
}

export function createPixelOfficeAdapterState(): PixelOfficeAdapterState {
  return {
    lastSeq: 0,
    castSpawned: false,
    processedIds: new Set(),
    dynamicAgents: {},
  }
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null
}

function specialistPayloadFromEvent(evt: SseEvent): SpecialistPixelAgentInput | null {
  const artifacts = asRecord(evt.artifacts)
  const payload = asRecord(artifacts?.specialist_agent) ?? asRecord((evt as Record<string, unknown>).specialist_agent)
  return payload as SpecialistPixelAgentInput | null
}

function specialistDescriptorFromEvent(evt: SseEvent): DynamicPixelAgent | null {
  const payload = specialistPayloadFromEvent(evt)
  if (!payload) return null
  const descriptor = specialistAgentMeta(payload)
  return {
    role: descriptor.role,
    id: descriptor.id,
    meta: descriptor.meta,
  }
}

export function applyBosskuEventsToPixelOffice(
  events: SseEvent[],
  state: PixelOfficeAdapterState,
): PixelOfficeHostMessage[] {
  const out: PixelOfficeHostMessage[] = []
  const sorted = [...events].sort((a, b) => Number(a.seq ?? 0) - Number(b.seq ?? 0))

  for (const evt of sorted) {
    const seq = Number(evt.seq ?? 0)
    const id = String(evt.id ?? `${evt.run_id}-${seq}-${evt.type}`)
    if (state.processedIds.has(id)) continue
    if (seq > 0 && seq <= state.lastSeq && state.processedIds.size > 0) {
      // allow same-seq only when not yet seen
    }
    state.processedIds.add(id)
    if (seq > state.lastSeq) state.lastSeq = seq
    const dynamicAgent = specialistDescriptorFromEvent(evt)
    if (dynamicAgent) {
      state.dynamicAgents[dynamicAgent.role] = dynamicAgent
    }

    const batch = mapSingleEvent(evt, state)
    if (String(evt.type ?? '') === 'run_started') {
      state.castSpawned = true
    }
    out.push(...batch)
  }

  return out
}

export function resetPixelOfficeAdapterState(state: PixelOfficeAdapterState): void {
  state.lastSeq = 0
  state.castSpawned = false
  state.processedIds.clear()
  state.dynamicAgents = {}
  toolSeq = 0
}
