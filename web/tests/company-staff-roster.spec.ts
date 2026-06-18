import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CompanyStaffRoster from '../components/CompanyStaffRoster.vue'

describe('CompanyStaffRoster', () => {
  it('renders seeded staff and exposes editable state labels', () => {
    const wrapper = mount(CompanyStaffRoster, {
      props: {
        staff: [
          {
            id: 'staff-1',
            role_slug: 'project-manager',
            display_name: 'Project Manager',
            description: 'Turns CEO goals into approved work.',
            trigger_keywords: ['planning'],
            staff_active: true,
            council_enabled: true,
            runtime_mode: 'mixed',
            approval_status: 'approved',
          },
          {
            id: 'staff-2',
            role_slug: 'seo-writer',
            display_name: 'SEO Writer',
            description: 'Improves search-focused content.',
            trigger_keywords: ['seo'],
            staff_active: false,
            council_enabled: false,
            runtime_mode: 'advisory',
            approval_status: 'approved',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('Project Manager')
    expect(wrapper.text()).toContain('mixed')
    expect(wrapper.text()).toContain('SEO Writer')
    expect(wrapper.text()).toContain('paused')
  })
})
