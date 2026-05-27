import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ChangeApprovalModal from '../components/ChangeApprovalModal.vue'
import SideBySideDiffViewer from '../components/SideBySideDiffViewer.vue'

const post = vi.fn().mockResolvedValue({ run_has_pending: false })

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

  it('shows why and summary in agent banner', () => {
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval: {
          ...approval,
          evidence: {
            ...approval.evidence,
            why: 'Security test coverage needed',
            summary: 'Add upload validation tests',
          },
        },
        pendingCount: 1,
        askingAgent: 'executor',
      },
      global: {
        stubs: { Teleport: true, PixelAgentPortrait: true },
      },
    })

    expect(wrapper.text()).toContain('Security test coverage needed')
    expect(wrapper.text()).toContain('Add upload validation tests')
  })

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
          PixelAgentPortrait: {
            template: '<div data-testid="pixel-agent-portrait-stub" />',
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="change-approval-dialog"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="approval-agent-banner"]').exists()).toBe(true)
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

  it('emits decided when API returns 422 not pending', async () => {
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
    expect(wrapper.emitted('decided')).toHaveLength(1)
    expect(wrapper.emitted('decided')?.[0]?.[0]).toMatchObject({ runHasPending: true })
  })

  it('emits decided with runHasPending true when more items remain', async () => {
    post.mockResolvedValueOnce({ run_has_pending: true })
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval,
        pendingCount: 3,
      },
      global: {
        stubs: { Teleport: true },
      },
    })

    const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'))
    await approveBtn!.trigger('click')
    expect(wrapper.emitted('decided')?.[0]?.[0]).toEqual({
      runHasPending: true,
      note: '',
    })
  })

  it('emits decided with runHasPending false when queue is drained', async () => {
    post.mockResolvedValueOnce({ run_has_pending: false })
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
    expect(wrapper.emitted('decided')?.[0]?.[0]).toEqual({
      runHasPending: false,
      note: '',
    })
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

  it('disables request changes without code review comment', () => {
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

    const requestBtn = wrapper.get('[data-testid="request-changes-btn"]')
    expect((requestBtn.element as HTMLButtonElement).disabled).toBe(true)
  })

  it('calls reject API with note when request changes is clicked', async () => {
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

    await wrapper.get('[data-testid="code-review-instructions"]').setValue('Use api routes file')
    await wrapper.get('[data-testid="request-changes-btn"]').trigger('click')

    expect(post).toHaveBeenCalledWith('/approvals/ap-1/reject', {
      note: 'Use api routes file',
    })
  })

  it('renders terminal command without file safety warning', () => {
    const wrapper = mount(ChangeApprovalModal, {
      props: {
        open: true,
        approval: {
          id: 'ap-cmd',
          operation_type: 'terminal_command',
          description: 'Run command: php artisan test',
          risk_level: 'medium',
          status: 'pending',
          evidence: { command: 'php artisan test' },
        },
        pendingCount: 2,
      },
      global: {
        stubs: { Teleport: true },
      },
    })

    expect(wrapper.text()).toContain('php artisan test')
    expect(wrapper.find('[data-testid="diff-review-warning"]').exists()).toBe(false)
    const approveBtn = wrapper.findAll('button').find(b => b.text().includes('Approve'))
    expect(approveBtn?.attributes('disabled')).toBeUndefined()
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

  it('command viewer does not show file empty-after warning', () => {
    const wrapper = mount(SideBySideDiffViewer, {
      props: {
        commandText: 'php artisan test',
        changeType: 'command',
      },
    })

    expect(wrapper.find('[data-testid="diff-review-warning"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('php artisan test')
  })
})
