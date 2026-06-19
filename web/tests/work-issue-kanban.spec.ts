import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkIssueKanban from '../components/WorkIssueKanban.vue'

describe('WorkIssueKanban', () => {
  it('groups approved work issues by Kanban status', () => {
    const wrapper = mount(WorkIssueKanban, {
      props: {
        updatingId: null,
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
  })

  it('renders unknown legacy statuses instead of hiding issues', () => {
    const wrapper = mount(WorkIssueKanban, {
      props: {
        issues: [
          {
            id: 'issue-legacy',
            title: 'Legacy paused task',
            status: 'paused',
            priority: 'medium',
            approval_state: 'approved',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('paused')
    expect(wrapper.text()).toContain('Legacy paused task')
  })

  it('disables the updating issue control', () => {
    const wrapper = mount(WorkIssueKanban, {
      props: {
        updatingId: 'issue-1',
        issues: [
          {
            id: 'issue-1',
            title: 'Build staff page',
            status: 'todo',
            priority: 'high',
            approval_state: 'approved',
          },
        ],
      },
    })

    expect(wrapper.find('[data-testid="issue-issue-1-status"]').attributes('disabled')).toBeDefined()
  })
})
