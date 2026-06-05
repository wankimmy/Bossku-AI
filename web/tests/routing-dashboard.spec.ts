import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RoutingDashboard from '../components/RoutingDashboard.vue'

describe('RoutingDashboard', () => {
  it('renders Ollama role models from metadata', () => {
    const wrapper = mount(RoutingDashboard, {
      props: {
        metadata: {
          routing_decision: {
            workflow: 'direct_answer',
            task_type: 'question',
            risk_level: 'low',
            skill: 'laravel',
            executor_profile: 'none',
            needs_executor: false,
            memory_mode: 'none',
          },
          models_resolved: {
            router: 'kimi-k2.6',
            direct_answer: 'kimi-k2.6',
          },
        },
      },
    })
    expect(wrapper.text()).toContain('direct_answer')
    expect(wrapper.text()).toContain('laravel')
    expect(wrapper.text()).toContain('Model backend')
    expect(wrapper.text()).toContain('Ollama')
    expect(wrapper.text()).toContain('Fast model')
    expect(wrapper.text()).toContain('Memory used')
    expect(wrapper.text()).not.toContain('OpenAI')
    expect(wrapper.text()).not.toContain('Anthropic')
  })
})
