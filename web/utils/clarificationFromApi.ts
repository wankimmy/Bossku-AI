import type { ClarificationQuestion, ClarificationRequest } from '~/types/clarification'
import {
  fallbackQuestionsFromEvent,
  parseClarificationQuestions,
  type ClarificationSseEvent,
} from './clarificationStream'

export type ClarificationApiPayload = {
  run_id?: string
  status?: string
  stage?: string | null
  clarification?: {
    summary?: string
    assumptions?: unknown[]
    questions?: unknown[]
    stage?: string
  } | null
}

export function parseClarificationApiResponse(
  data: ClarificationApiPayload,
  fallbackRunId?: string | null,
): ClarificationRequest | null {
  const runId = String(data.run_id ?? fallbackRunId ?? '')
  if (!runId) return null

  if (data.status !== undefined && data.status !== 'awaiting_input') {
    return null
  }

  const clar = data.clarification
  if (clar === null || clar === undefined || typeof clar !== 'object') {
    return null
  }

  const record = clar as Record<string, unknown>
  const summary = String(record.summary ?? '').trim()
  const stage = String(data.stage ?? record.stage ?? '')

  let questions = parseClarificationQuestions(
    Array.isArray(record.questions) ? record.questions : [],
  )

  if (questions.length === 0 && summary !== '') {
    const evt: ClarificationSseEvent = { summary, message: summary, stage }
    questions = fallbackQuestionsFromEvent(evt)
  }

  if (questions.length === 0) {
    return null
  }

  const assumptions = Array.isArray(record.assumptions)
    ? record.assumptions.map(String)
    : []

  return {
    runId,
    stage,
    summary,
    assumptions,
    questions,
  }
}
