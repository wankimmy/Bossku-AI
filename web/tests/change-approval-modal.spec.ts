import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ChangeApprovalModal from '../components/ChangeApprovalModal.vue'
import SideBySideDiffViewer from '../components/SideBySideDiffViewer.vue'

const post = vi.fn().mockResolvedValue({})

vi.mock('../composables/useApi', () => ({
  useApi: () => ({ post }),
}))

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
      },
    })

    expect(wrapper.find('[data-testid="change-approval-dialog"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="side-by-side-diff"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Review before continuing')
    expect(wrapper.text()).toContain('Previous')
    expect(wrapper.text()).toContain('Updated')
  })

  it('calls approve API when Approve & apply is clicked', async () => {
    post.mockClear()
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval,
        pendingCount: 1,
      },
      global: {
        stubs: { Teleport: true },
      },
    })

    const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'))
    await approveBtn!.trigger('click')
    expect(post).toHaveBeenCalledWith('/approvals/ap-1/approve', {
      note: undefined,
    })
  })

  it('emits approve when API returns 422 not pending', async () => {
    post.mockRejectedValueOnce({
      status: 422,
      data: { message: 'Approval is not pending.' },
    })
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval,
        pendingCount: 1,
      },
      global: {
        stubs: { Teleport: true },
      },
    })

    const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'))
    await approveBtn!.trigger('click')
    expect(wrapper.emitted('approve')).toHaveLength(1)
  })

  it('disables approve for placeholder wipe proposal', () => {
    const before = Array.from({ length: 25 }, (_, i) => `// line ${i}`).join('\n')
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval: {
          id: 'ap-wipe',
          operation_type: 'file_write',
          description: 'Modify ReceiptController.php',
          risk_level: 'low',
          status: 'pending',
          review_blocked: true,
          review_block_reason: 'Proposed file content is placeholder text, not complete file contents.',
          evidence: {
            path: 'app/Http/Controllers/ReceiptController.php',
            change_type: 'modified',
            before,
            after: 'Will be determined after reading the file',
          },
        },
        pendingCount: 1,
      },
      global: {
        stubs: { Teleport: true },
      },
    })

    const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'))
    expect(approveBtn?.attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="diff-review-warning"]').exists()).toBe(true)
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
