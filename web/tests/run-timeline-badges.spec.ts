import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RunTimelineBadges from '../components/RunTimelineBadges.vue'

describe('RunTimelineBadges', () => {
  it('renders workflow, latest specialist, council state, and risk', () => {
    const wrapper = mount(RunTimelineBadges, {
      props: {
        routing: {
          backend: 'Ollama',
          workflow: 'writer_only',
          riskLevel: 'low',
        },
        messages: [
          {
            id: '1',
            agent: 'seo-writer',
            title: 'SEO Writer',
            status: 'completed',
            artifacts: {
              specialist_agent: {
                display_name: 'SEO Writer',
                match_score: 12,
                match_reason: 'intent_role',
              },
            },
          },
          {
            id: '2',
            agent: 'sales-manager',
            title: 'Sales Manager',
            status: 'completed',
            artifacts: {
              specialist_agent: {
                display_name: 'Sales Manager',
                match_score: 14,
                match_reason: 'keyword:sales',
              },
            },
          },
        ],
        aiCouncil: {
          status: 'skipped',
          reason: 'trivial_prompt',
          voices: [],
        },
      },
    })

    expect(wrapper.text()).toContain('Writer specialist')
    expect(wrapper.text()).toContain('Sales Manager')
    expect(wrapper.text()).toContain('AI council skipped')
    expect(wrapper.text()).toContain('Risk low')
  })
})
