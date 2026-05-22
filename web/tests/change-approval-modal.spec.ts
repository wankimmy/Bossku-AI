import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ChangeApprovalModal from '../components/ChangeApprovalModal.vue'
import SideBySideDiffViewer from '../components/SideBySideDiffViewer.vue'

describe('ChangeApprovalModal', () => {
  const approval = {
    id: 'ap-1',
    operation_type: 'file_write',
    description: 'Modify routes/web.php',
    risk_level: 'medium',
    status: 'pending',
    evidence: {
      path: 'routes/web.php',
      change_type: 'modified',
      before: "Route::get('/old');\n",
      after: "Route::get('/new');\n",
      diff: "--- routes/web.php\n+++ routes/web.php\n-Route::get('/old');\n+Route::get('/new');\n",
    },
  }

  it('renders enlarged dialog with side-by-side diff', () => {
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval,
        pendingCount: 1,
      },
      global: {
        stubs: {
          Teleport: true,
        },
        mocks: {
          useApi: () => ({
            post: async () => ({}),
          }),
        },
      },
    })

    expect(wrapper.find('[data-testid="change-approval-dialog"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="side-by-side-diff"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Review before continuing')
    expect(wrapper.text()).toContain('Previous')
    expect(wrapper.text()).toContain('Updated')
  })

  it('highlights remove and add cells in split viewer', () => {
    const wrapper = mount(SideBySideDiffViewer, {
      props: {
        path: 'routes/web.php',
        changeType: 'modified',
        before: "line one\nline two\n",
        after: "line ONE\nline two\n",
      },
    })

    expect(wrapper.find('.text-rose-200').exists()).toBe(true)
    expect(wrapper.find('.text-emerald-200').exists()).toBe(true)
  })
})
