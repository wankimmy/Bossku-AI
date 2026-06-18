import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PlanOverview from '../components/PlanOverview.vue'

describe('PlanOverview', () => {
  it('renders council dissent and bounded stop conditions', () => {
    const wrapper = mount(PlanOverview, {
      props: {
        plan: {
          goal: 'Add council plan review',
          taskSummary: 'Council Plan Review V1',
          keyDesignDecisions: [],
          flowSteps: [],
          notes: [],
          risks: [],
          todos: [],
          councilReview: {
            status: 'completed',
            consensus: 'Reuse planner review before execution.',
            strongest_dissent: 'Do not build unbounded peer chat in V1.',
            recommended_adjustments: ['Show dissent before approval.'],
            stop_conditions: ['Stop after configured revision rounds.'],
            voices: [
              { id: 'skeptic', label: 'Skeptic', position: 'Do not build unbounded peer chat in V1.' },
            ],
          },
        },
      },
      global: {
        stubs: {
          ClientOnly: { template: '<div><slot /></div>' },
          MermaidDiagram: true,
          PlanChecklist: true,
          UiEmptyState: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Council review')
    expect(wrapper.text()).toContain('Do not build unbounded peer chat in V1.')
    expect(wrapper.text()).toContain('Stop after configured revision rounds.')
  })
})
