import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ClarificationPanel from '../components/ClarificationPanel.vue'

const threeOptions = [
  { id: 'full', label: 'Full repo audit', recommendation: true },
  { id: 'narrow', label: 'Targeted paths only' },
  { id: 'explain', label: 'Explain only — no changes' },
]

describe('ClarificationPanel', () => {
  it('renders three choice buttons, text input, and emits submit payload', async () => {
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
    expect(wrapper.text()).toContain('Full repo audit')

    const choiceButtons = wrapper.findAll('button').filter(b =>
      threeOptions.some(o => b.text().includes(o.label)),
    )
    expect(choiceButtons).toHaveLength(3)

    const input = wrapper.get('[data-testid="clarification-input"]')
    expect(input.exists()).toBe(true)

    await choiceButtons[0]!.trigger('click')
    expect((input.element as HTMLInputElement).value).toContain('Full repo audit')

    const continueBtn = wrapper.get('[data-testid="clarification-continue"]')
    await continueBtn.trigger('click')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')?.[0]?.[0] as Array<{
      question_id: string
      option_id?: string
      free_text?: string
    }>
    expect(payload?.[0]?.question_id).toBe('q1')
    expect(payload?.[0]?.option_id).toBe('full')
    expect(payload?.[0]?.free_text).toContain('Full repo audit')
  })

  it('allows submit with typed answer only', async () => {
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

    const input = wrapper.get('[data-testid="clarification-input"]')
    await input.setValue('Only the API layer')

    const continueBtn = wrapper.get('[data-testid="clarification-continue"]')
    expect((continueBtn.element as HTMLButtonElement).disabled).toBe(false)
    await continueBtn.trigger('click')

    const payload = wrapper.emitted('submit')?.[0]?.[0] as Array<{
      question_id: string
      free_text?: string
    }>
    expect(payload?.[0]?.free_text).toBe('Only the API layer')
  })
})
