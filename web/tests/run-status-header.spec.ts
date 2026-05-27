import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DashboardStatCard from '../components/DashboardStatCard.vue'
import RunStatusHeader from '../components/RunStatusHeader.vue'

const globalComponents = { DashboardStatCard }

describe('RunStatusHeader', () => {
  it('renders status and model dashboard cards with distinct variants', () => {
    const wrapper = mount(RunStatusHeader, {
      props: {
        running: true,
        status: 'running',
        memoryUsed: true,
        routing: {
          reasoningModel: 'kimi-k2.6:cloud',
          codingModel: 'qwen3-coder-next:cloud',
          reviewModel: 'deepseek-v4-pro:cloud',
          fastModel: 'kimi-k2.6:cloud',
        },
      },
      global: { components: globalComponents },
    })

    expect(wrapper.find('[data-testid="run-status-header"]').exists()).toBe(true)
    expect(wrapper.find('[data-variant="status"]').classes()).toContain('border-emerald-800/60')
    expect(wrapper.find('[data-variant="backend"]').classes()).toContain('border-indigo-800/50')
    expect(wrapper.find('[data-variant="memory"]').classes()).toContain('border-violet-800/50')

    const modelCards = wrapper.find('[data-testid="run-status-model-cards"]')
    expect(modelCards.exists()).toBe(true)
    expect(modelCards.find('[data-variant="reasoning"]').classes()).toContain('border-blue-800/50')
    expect(modelCards.find('[data-variant="coding"]').classes()).toContain('border-emerald-800/50')
    expect(modelCards.find('[data-variant="review"]').classes()).toContain('border-amber-800/50')
    expect(modelCards.find('[data-variant="fast"]').classes()).toContain('border-cyan-800/50')

    expect(wrapper.text()).toContain('kimi-k2.6:cloud')
    expect(wrapper.text()).toContain('running')
  })

  it('uses idle styling when not running', () => {
    const wrapper = mount(RunStatusHeader, {
      props: {
        running: false,
        status: 'idle',
        memoryUsed: false,
      },
      global: { components: globalComponents },
    })

    expect(wrapper.find('[data-variant="status"]').classes()).toContain('border-zinc-300')
    expect(wrapper.find('[data-testid="run-status-model-cards"]').exists()).toBe(false)
  })
})
