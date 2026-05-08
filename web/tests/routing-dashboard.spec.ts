import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RoutingDashboard from '../components/RoutingDashboard.vue'

describe('RoutingDashboard', () => {
  it('renders workflow from metadata', () => {
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
            router: 'gpt-test',
            direct_answer: 'gpt-test',
          },
        },
      },
    })
    expect(wrapper.text()).toContain('direct_answer')
    expect(wrapper.text()).toContain('laravel')
    expect(wrapper.text()).toContain('Memory Used: no')
  })
})
