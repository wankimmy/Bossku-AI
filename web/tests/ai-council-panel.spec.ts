import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AiCouncilPanel from '../components/AiCouncilPanel.vue'

describe('AiCouncilPanel', () => {
  it('renders council voices and intent', () => {
    const wrapper = mount(AiCouncilPanel, {
      props: {
        council: {
          status: 'completed',
          intent: 'seo',
          consensus: 'Council reviewed the draft and synthesized one final answer.',
          voices: [
            {
              role_slug: 'seo-writer',
              display_name: 'SEO Writer',
              critique: 'Verify headings map to search intent.',
            },
          ],
        },
      },
    })

    expect(wrapper.text()).toContain('AI council')
    expect(wrapper.text()).toContain('seo')
    expect(wrapper.text()).toContain('SEO Writer')
    expect(wrapper.text()).toContain('search intent')
  })

  it('renders skipped reasons and clarification questions', () => {
    const wrapper = mount(AiCouncilPanel, {
      props: {
        council: {
          status: 'needs_clarification',
          reason: 'missing_information',
          voices: [],
          questions: [
            { id: 'audience', prompt: 'Who is the target audience?' },
          ],
        },
      },
    })

    expect(wrapper.text()).toContain('missing_information')
    expect(wrapper.text()).toContain('Who is the target audience?')
  })
})
