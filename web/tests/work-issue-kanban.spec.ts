import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkIssueKanban from '../components/WorkIssueKanban.vue'

describe('WorkIssueKanban', () => {
  it('groups approved work issues by Kanban status and emits status changes', async () => {
    const wrapper = mount(WorkIssueKanban, {
      props: {
        issues: [
          {
            id: 'issue-1',
            title: 'Build staff page',
            status: 'todo',
            priority: 'high',
            approval_state: 'approved',
            assignee_role_slug: 'tech-lead',
          },
          {
            id: 'issue-2',
            title: 'Review copy',
            status: 'in_review',
            priority: 'medium',
            approval_state: 'approved',
            assignee_role_slug: 'marketing-manager',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('Todo')
    expect(wrapper.text()).toContain('In Review')
    expect(wrapper.text()).toContain('Build staff page')
    expect(wrapper.text()).toContain('Review copy')

    await wrapper.find('[data-testid="issue-issue-1-status"]').setValue('in_progress')

    expect(wrapper.emitted('update-status')?.[0]).toEqual(['issue-1', 'in_progress'])
  })
})
