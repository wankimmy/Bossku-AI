import type { SseEvent } from '~/composables/useRunStream'

const TERMINAL_EVENT_TYPES = new Set([
  'run_completed',
  'run_failed',
  'planner_failed',
])

export function isTerminalStreamEvent(evt: SseEvent): boolean {
  const type = String(evt.type ?? '')
  return TERMINAL_EVENT_TYPES.has(type)
}

export function isRunStatusTerminal(status: string): boolean {
  return ['completed', 'failed', 'cancelled'].includes(status)
}
