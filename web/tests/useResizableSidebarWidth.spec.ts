import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import {
  clampOfficeSidebarWidth,
  defaultOfficeSidebarWidth,
  maxOfficeSidebarWidth,
  OFFICE_SIDEBAR_MIN_WIDTH,
  useResizableSidebarWidth,
} from '../composables/useResizableSidebarWidth'

describe('useResizableSidebarWidth helpers', () => {
  it('clamps width between min and max for viewport', () => {
    expect(clampOfficeSidebarWidth(100, 1280)).toBe(OFFICE_SIDEBAR_MIN_WIDTH)
    expect(clampOfficeSidebarWidth(2000, 1280)).toBe(maxOfficeSidebarWidth(1280))
  })

  it('defaults to a 50/50 split for typical desktop widths', () => {
    expect(defaultOfficeSidebarWidth(1280)).toBe(640)
    expect(defaultOfficeSidebarWidth(1920)).toBe(960)
  })

  it('caps max width on narrow viewports', () => {
    expect(maxOfficeSidebarWidth(800)).toBe(520)
    expect(clampOfficeSidebarWidth(500, 800)).toBe(500)
  })

  it('updates width via setWidth', async () => {
    const Comp = defineComponent({
      setup() {
        const { width, setWidth } = useResizableSidebarWidth()
        return { width, setWidth }
      },
      template: '<span data-testid="w">{{ width }}</span>',
    })
    const wrapper = mount(Comp)
    wrapper.vm.setWidth(420)
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[data-testid="w"]').text()).toBe('420')
  })
})
