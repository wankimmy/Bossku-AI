import { describe, expect, it } from 'vitest'
import { parseClarificationApiResponse } from '../utils/clarificationFromApi'
import {
  buildClarificationRequest,
  isAwaitingClarification,
} from '../utils/clarificationStream'

describe('clarification stream handling', () => {
  it('recognizes clarification_requested as non-terminal pause', () => {
    const events = [
      { type: 'run_started' },
      { type: 'clarification_requested', run_id: 'run-1', summary: 'Need scope' },
    ]
    expect(isAwaitingClarification(events)).toBe(true)
  })

  it('does not treat approval_requested as clarification even with awaiting_input', () => {
    expect(isAwaitingClarification([{
      type: 'approval_requested',
      status: 'awaiting_input',
      to_agent: 'user',
      summary: '3 change(s) need your approval',
    }])).toBe(false)
  })

  it('detects awaiting_input handoff to user as clarification pause', () => {
    const events = [
      { type: 'memory_retrieved', status: 'success' },
      {
        type: 'orchestrator',
        status: 'awaiting_input',
        to_agent: 'user',
        summary: 'Need pricing model before proceeding.',
      },
    ]
    expect(isAwaitingClarification(events)).toBe(true)
    const req = buildClarificationRequest(events, 'run-1')
    expect(req?.questions[0].prompt).toContain('pricing')
  })

  it('stays awaiting when clarification is not the last event type but nothing terminal after', () => {
    const events = [
      { type: 'clarification_requested', run_id: 'run-1', summary: 'Confirm scope' },
      { type: 'model_router_done' },
    ]
    expect(isAwaitingClarification(events)).toBe(true)
  })

  it('clears awaiting after clarification_received', () => {
    const events = [
      { type: 'clarification_requested', run_id: 'run-1' },
      { type: 'clarification_received' },
    ]
    expect(isAwaitingClarification(events)).toBe(false)
  })

  it('builds fallback question from summary when questions array missing', () => {
    const req = buildClarificationRequest(
      [{
        type: 'clarification_requested',
        run_id: 'run-1',
        status: 'awaiting_input',
        summary: 'Portfolio enhancement needs pricing and USP details.',
      }],
      null,
    )
    expect(req).not.toBeNull()
    expect(req!.questions).toHaveLength(1)
    expect(req!.questions[0].options).toHaveLength(3)
    expect(req!.questions[0].prompt).toContain('Portfolio enhancement')
  })

  it('parses nested questions from artifacts.clarification', () => {
    const req = buildClarificationRequest(
      [{
        type: 'clarification_requested',
        run_id: 'run-1',
        summary: 'Summary line',
        artifacts: {
          clarification: {
            questions: [{
              id: 'q1',
              prompt: 'How deep?',
              options: [
                { id: 'a', label: 'Full', recommendation: true },
                { id: 'b', label: 'Partial' },
              ],
            }],
          },
        },
      }],
      null,
    )
    expect(req?.questions[0].prompt).toBe('How deep?')
    expect(req?.questions[0].options).toHaveLength(2)
  })

  it('preserves single question from SSE without adding more questions', () => {
    const req = buildClarificationRequest(
      [{
        type: 'clarification_requested',
        run_id: 'run-1',
        questions: [{
          id: 'q1',
          prompt: 'Which environment?',
          options: [
            { id: 'local', label: 'Local' },
            { id: 'prod', label: 'Production', recommendation: true },
          ],
        }],
      }],
      null,
    )
    expect(req?.questions).toHaveLength(1)
    expect(req?.questions[0].prompt).toBe('Which environment?')
  })
})

describe('clarification API response parsing', () => {
  it('maps checkpoint clarification to ClarificationRequest', () => {
    const req = parseClarificationApiResponse({
      run_id: 'run-api-1',
      status: 'awaiting_input',
      stage: 'pre_execution',
      clarification: {
        summary: 'Confirm pricing model.',
        assumptions: ['B2B SaaS'],
        questions: [{
          id: 'q1',
          prompt: 'Which pricing?',
          options: [
            { id: 'tier', label: 'Tiered', recommendation: true },
            { id: 'flat', label: 'Flat' },
          ],
        }],
      },
    }, null)

    expect(req?.runId).toBe('run-api-1')
    expect(req?.questions[0].prompt).toBe('Which pricing?')
    expect(req?.questions[0].options).toHaveLength(2)
    expect(req?.assumptions).toEqual(['B2B SaaS'])
  })

  it('returns null when status is not awaiting_input', () => {
    expect(parseClarificationApiResponse({
      run_id: 'run-1',
      status: 'running',
      clarification: { summary: 'x', questions: [] },
    }, null)).toBeNull()
  })

  it('builds fallback question from summary when questions missing', () => {
    const req = parseClarificationApiResponse({
      run_id: 'run-2',
      status: 'awaiting_input',
      clarification: {
        summary: 'Need deployment target before continuing.',
      },
    }, null)

    expect(req?.questions).toHaveLength(1)
    expect(req?.questions[0].prompt).toContain('deployment target')
  })
})
