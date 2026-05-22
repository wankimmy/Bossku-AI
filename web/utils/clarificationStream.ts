import type { ClarificationQuestion, ClarificationRequest } from '~/types/clarification'

export type ClarificationSseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
  summary?: string
  message?: string
  stage?: string
  questions?: unknown[]
  assumptions?: unknown[]
  artifacts?: Record<string, unknown>
}

export const CLARIFICATION_TERMINAL_AFTER = new Set([
  'clarification_received',
  'run_completed',
  'run_failed',
  'planner_failed',
])

export const DEFAULT_CLARIFICATION_OPTIONS = [
  { id: 'proceed', label: 'Proceed with your best interpretation', recommendation: true },
  { id: 'narrow', label: 'Start narrow — minimal scope first', recommendation: false },
  { id: 'explain', label: 'Explain options only — no changes yet', recommendation: false },
] as const

function isClarificationPauseEvent(evt: ClarificationSseEvent | undefined): boolean {
  if (!evt) return false
  const type = String(evt.type ?? '')
  if (type === 'approval_requested' || type === 'approval_feedback_received') return false
  if (String(evt.stage ?? '') === 'executor_approvals') return false
  if (type === 'clarification_requested') return true
  if (String(evt.status) !== 'awaiting_input') return false
  const toAgent = evt.to_agent
  if (toAgent !== undefined && toAgent !== null && String(toAgent) !== 'user') return false
  if (Array.isArray(evt.questions) && evt.questions.length > 0) return true
  const artifacts = evt.artifacts
  if (artifacts !== null && typeof artifacts === 'object') {
    const clarification = (artifacts as Record<string, unknown>).clarification
    if (clarification !== null && typeof clarification === 'object') return true
  }
  return type === 'orchestrator' || type.includes('clarification')
}

export function lastClarificationEventIndex(events: ClarificationSseEvent[]): number {
  for (let i = events.length - 1; i >= 0; i--) {
    if (isClarificationPauseEvent(events[i])) {
      return i
    }
  }
  return -1
}

/** True when a clarification pause is active (not superseded by continue/terminal events). */
export function isAwaitingClarification(events: ClarificationSseEvent[]): boolean {
  const idx = lastClarificationEventIndex(events)
  if (idx === -1) {
    return false
  }
  for (let i = idx + 1; i < events.length; i++) {
    const type = String(events[i]?.type ?? '')
    if (CLARIFICATION_TERMINAL_AFTER.has(type)) {
      return false
    }
  }
  return true
}

function rawQuestionsFromEvent(evt: ClarificationSseEvent): unknown[] {
  if (Array.isArray(evt.questions)) {
    return evt.questions
  }
  const artifacts = evt.artifacts
  if (artifacts !== null && typeof artifacts === 'object') {
    const clarification = (artifacts as Record<string, unknown>).clarification
    if (clarification !== null && typeof clarification === 'object') {
      const nested = (clarification as Record<string, unknown>).questions
      if (Array.isArray(nested)) {
        return nested
      }
    }
  }
  return []
}

export function parseClarificationQuestions(rawQuestions: unknown[]): ClarificationQuestion[] {
  return rawQuestions
    .filter((q): q is Record<string, unknown> => q !== null && typeof q === 'object')
    .map((q, idx) => {
      const options = (Array.isArray(q.options) ? q.options : [])
        .filter((o): o is Record<string, unknown> => o !== null && typeof o === 'object')
        .map((o, oIdx) => ({
          id: String(o.id ?? `opt${oIdx + 1}`),
          label: String(o.label ?? ''),
          recommendation: Boolean(o.recommendation),
        }))
        .filter(o => o.label !== '')

      const padded = [...options.slice(0, 3)]
      for (let i = padded.length; i < 3; i++) {
        const def = DEFAULT_CLARIFICATION_OPTIONS[i]
        padded.push({
          id: def.id,
          label: def.label,
          recommendation: def.recommendation,
        })
      }

      return {
        id: String(q.id ?? `q${idx + 1}`),
        prompt: String(q.prompt ?? ''),
        why_it_matters: q.why_it_matters != null ? String(q.why_it_matters) : undefined,
        allow_free_text: q.allow_free_text !== false,
        options: padded,
      }
    })
    .filter(q => q.prompt !== '')
}

export function fallbackQuestionsFromEvent(evt: ClarificationSseEvent): ClarificationQuestion[] {
  const summary = String(evt.summary ?? evt.message ?? '').trim()
  if (summary === '') {
    return []
  }

  return [{
    id: 'q1',
    prompt: summary,
    why_it_matters: 'Your answer guides what BosskuAI does next.',
    allow_free_text: true,
    options: DEFAULT_CLARIFICATION_OPTIONS.map(o => ({ ...o })),
  }]
}

export function buildClarificationRequest(
  events: ClarificationSseEvent[],
  activeRunId: string | null,
): ClarificationRequest | null {
  const idx = lastClarificationEventIndex(events)
  if (idx === -1 || !isAwaitingClarification(events)) {
    return null
  }

  const evt = events[idx]!
  let questions = parseClarificationQuestions(rawQuestionsFromEvent(evt))
  if (questions.length === 0) {
    questions = fallbackQuestionsFromEvent(evt)
  }
  if (questions.length === 0) {
    return null
  }

  let assumptions: string[] = []
  if (Array.isArray(evt.assumptions)) {
    assumptions = evt.assumptions.map(String)
  }
  else {
    const artifacts = evt.artifacts
    if (artifacts !== null && typeof artifacts === 'object') {
      const clarification = (artifacts as Record<string, unknown>).clarification
      if (clarification !== null && typeof clarification === 'object') {
        const nested = (clarification as Record<string, unknown>).assumptions
        if (Array.isArray(nested)) {
          assumptions = nested.map(String)
        }
      }
    }
  }

  const artifacts = evt.artifacts
  let proof: ClarificationRequest['proof'] | undefined
  let fromAgent: string | undefined
  let origin: string | undefined
  if (artifacts !== null && typeof artifacts === 'object') {
    const clar = (artifacts as Record<string, unknown>).clarification
    if (clar !== null && typeof clar === 'object') {
      const c = clar as Record<string, unknown>
      if (c.proof !== null && typeof c.proof === 'object') {
        proof = c.proof as ClarificationRequest['proof']
      }
      fromAgent = c.from_agent != null ? String(c.from_agent) : undefined
      origin = c.origin != null ? String(c.origin) : undefined
    }
    if (! proof && (artifacts as Record<string, unknown>).proof != null) {
      proof = (artifacts as Record<string, unknown>).proof as ClarificationRequest['proof']
    }
  }
  fromAgent = fromAgent ?? (evt.from_agent != null ? String(evt.from_agent) : undefined)
  origin = origin ?? (evt.origin != null ? String(evt.origin) : undefined)

  return {
    runId: String(evt.run_id ?? activeRunId ?? ''),
    stage: String(evt.stage ?? ''),
    summary: String(evt.summary ?? evt.message ?? ''),
    assumptions,
    questions,
    from_agent: fromAgent,
    origin,
    proof,
  }
}
