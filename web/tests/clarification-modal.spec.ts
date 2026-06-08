import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ClarificationModal from '../components/ClarificationModal.vue'
import ClarificationPanel from '../components/ClarificationPanel.vue'
import type { ClarificationRequest } from '../types/clarification'

const modalGlobal = {
  stubs: { Teleport: true },
  components: { ClarificationPanel },
}

const sampleRequest: ClarificationRequest = {
  runId: 'run-abc',
  stage: 'pre_execution',
  summary: 'Need your preference on scope.',
  assumptions: ['Assume local dev environment'],
  questions: [{
    id: 'q1',
    prompt: 'How deep should we go?',
    options: [
      { id: 'a', label: 'Full scope', recommendation: true },
      { id: 'b', label: 'Minimal', recommendation: false },
      { id: 'c', label: 'Explain only', recommendation: false },
    ],
    allow_free_text: true,
  }],
}

describe('ClarificationModal', () => {
  it('renders when open with dialog semantics', () => {
    const wrapper = mount(ClarificationModal, {
      props: {
        open: true,
        request: sampleRequest,
      },
      global: modalGlobal,
    })

    const dialog = wrapper.find('[data-testid="clarification-modal"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('role')).toBe('dialog')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(wrapper.text()).toContain('BosskuAI needs your input')
    expect(wrapper.text()).toContain('How deep should we go?')
  })

  it('does not render overlay when closed', () => {
    const wrapper = mount(ClarificationModal, {
      props: {
        open: false,
        request: sampleRequest,
      },
      global: modalGlobal,
    })

    expect(wrapper.find('[data-testid="clarification-modal"]').exists()).toBe(false)
  })

  it('emits submit when Approve & continue is clicked after selecting an option', async () => {
    const wrapper = mount(ClarificationModal, {
      props: {
        open: true,
        request: sampleRequest,
      },
      global: modalGlobal,
    })

    const option = wrapper.findAll('button').find(b => b.text().includes('Full scope'))
    expect(option).toBeDefined()
    await option!.trigger('click')

    const approveBtn = wrapper.find('[data-testid="clarification-approve"]')
    await approveBtn.trigger('click')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')![0][0] as {
      review_decision: string
      answers: Array<{ question_id: string; option_id?: string }>
    }
    expect(payload.review_decision).toBe('approve')
    expect(payload.answers[0].question_id).toBe('q1')
    expect(payload.answers[0].option_id).toBe('a')
  })

  it('uses master plan review copy for planner review stage', () => {
    const wrapper = mount(ClarificationModal, {
      props: {
        open: true,
        request: {
          ...sampleRequest,
          stage: 'planner_review',
          summary: 'Review the master plan before execution.',
        },
      },
      global: modalGlobal,
    })

    expect(wrapper.text()).toContain('Review master plan')
    expect(wrapper.text()).toContain('Plan feedback')
    expect(wrapper.text()).toContain('Approve plan & execute')
  })
})
