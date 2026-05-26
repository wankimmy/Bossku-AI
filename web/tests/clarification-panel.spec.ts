import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ClarificationPanel from '../components/ClarificationPanel.vue'

const threeOptions = [
  { id: 'full', label: 'Full repo audit', recommendation: true },
  { id: 'narrow', label: 'Targeted paths only' },
  { id: 'explain', label: 'Explain only — no changes' },
]

describe('ClarificationPanel', () => {
  it('renders choice buttons and emits approve payload', async () => {
    const wrapper = mount(ClarificationPanel, {
      props: {
        runId: 'run-1',
        summary: 'Need your input',
        questions: [
          {
            id: 'q1',
            prompt: 'How deep should the audit go?',
            why_it_matters: 'Avoids wasted tokens',
            options: threeOptions,
            allow_free_text: true,
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('How deep should the audit go?')
    expect(wrapper.text()).toContain('Code review instructions')

    const choiceButtons = wrapper.findAll('button').filter(b =>
      threeOptions.some(o => b.text().includes(o.label)),
    )
    expect(choiceButtons).toHaveLength(3)

    await choiceButtons[0]!.trigger('click')

    const approveBtn = wrapper.get('[data-testid="clarification-approve"]')
    await approveBtn.trigger('click')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')?.[0]?.[0] as {
      review_decision: string
      answers: Array<{ question_id: string; option_id?: string; free_text?: string }>
    }
    expect(payload.review_decision).toBe('approve')
    expect(payload.answers[0]?.question_id).toBe('q1')
    expect(payload.answers[0]?.option_id).toBe('full')
  })

  it('emits request_changes with code review comment', async () => {
    const wrapper = mount(ClarificationPanel, {
      props: {
        runId: 'run-1',
        questions: [
          {
            id: 'q1',
            prompt: 'Preferred scope?',
            options: threeOptions,
            allow_free_text: true,
          },
        ],
      },
    })

    const requestBtn = wrapper.get('[data-testid="clarification-request-changes"]')
    expect((requestBtn.element as HTMLButtonElement).disabled).toBe(true)

    await wrapper.get('[data-testid="clarification-code-review"]').setValue('Use Form Request validation')

    expect((requestBtn.element as HTMLButtonElement).disabled).toBe(false)
    await requestBtn.trigger('click')

    const payload = wrapper.emitted('submit')?.[0]?.[0] as {
      review_decision: string
      code_review_comment?: string
    }
    expect(payload.review_decision).toBe('request_changes')
    expect(payload.code_review_comment).toBe('Use Form Request validation')
  })
})
