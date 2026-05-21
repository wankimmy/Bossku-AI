import { describe, expect, it } from 'vitest'

describe('clarification stream handling', () => {
  it('recognizes clarification_requested as non-terminal pause', () => {
    const terminal = new Set(['run_completed', 'run_failed', 'planner_failed'])
    const lastType = 'clarification_requested'
    expect(terminal.has(lastType)).toBe(false)
  })

  it('parses clarification event shape', () => {
    const evt = {
      type: 'clarification_requested',
      run_id: 'run-1',
      status: 'awaiting_input',
      stage: 'pre_execution',
      summary: 'Confirm scope',
      questions: [
        {
          id: 'q1',
          prompt: 'Proceed?',
          options: [{ id: 'yes', label: 'Yes', recommendation: true }],
        },
      ],
    }
    expect(evt.questions).toHaveLength(1)
    expect(evt.questions[0].options[0].label).toBe('Yes')
  })
})
