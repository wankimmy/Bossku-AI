import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StaffCouncilPanel from '../components/StaffCouncilPanel.vue'

describe('StaffCouncilPanel', () => {
  it('renders staff voices, recommendations, issue breakdown, and stop conditions', () => {
    const wrapper = mount(StaffCouncilPanel, {
      props: {
        council: {
          status: 'completed',
          voices: [
            {
              role_slug: 'tech-lead',
              display_name: 'Tech Lead',
              position: 'Keep implementation bounded.',
              recommendations: ['Create approved work issues from plan items.'],
            },
          ],
          staff_recommendations: ['Ask CEO before starting follow-up work.'],
          issue_breakdown: [
            {
              plan_item_id: 'plan-1',
              title: 'Build Kanban board',
              assignee_role_slug: 'tech-lead',
              priority: 'high',
            },
          ],
          stop_conditions: ['Wait for CEO approval before starting more work.'],
        },
      },
    })

    expect(wrapper.text()).toContain('Staff council')
    expect(wrapper.text()).toContain('Tech Lead')
    expect(wrapper.text()).toContain('Ask CEO before starting follow-up work.')
    expect(wrapper.text()).toContain('Build Kanban board')
    expect(wrapper.text()).toContain('Wait for CEO approval')
  })
})
