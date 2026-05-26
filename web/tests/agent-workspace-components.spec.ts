import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgentHandoffFlow from '../components/AgentHandoffFlow.vue'
import AgentMessageCard from '../components/AgentMessageCard.vue'
import PlanChecklist from '../components/PlanChecklist.vue'
import ChangeTrackerPanel from '../components/ChangeTrackerPanel.vue'
import AuditFindingsPanel from '../components/AuditFindingsPanel.vue'
import FinalResultPanel from '../components/FinalResultPanel.vue'
import RiskItemList from '../components/RiskItemList.vue'
import FileDiffViewer from '../components/FileDiffViewer.vue'
import LandingConversationTabs from '../components/LandingConversationTabs.vue'
import TestResultPanel from '../components/TestResultPanel.vue'
import UiEmptyState from '../components/ui/EmptyState.vue'

describe('agent workspace components', () => {
  it('renders handoff nodes with status labels', () => {
    const wrapper = mount(AgentHandoffFlow, {
      props: {
        nodes: [
          { agent: 'orchestrator', label: 'Orchestrator', status: 'completed' },
          { agent: 'executor', label: 'Executor', status: 'running' },
        ],
      },
    })

    expect(wrapper.text()).toContain('Orchestrator')
    expect(wrapper.text()).toContain('running')
  })

  it('renders planner checklist items with owners', () => {
    const wrapper = mount(PlanChecklist, {
      props: {
        items: [
          { id: 'plan-1', title: 'Inspect relevant files', description: 'Read targeted files.', owner: 'executor', status: 'completed' },
        ],
      },
      global: {
        components: { UiEmptyState },
      },
    })

    expect(wrapper.text()).toContain('Inspect relevant files')
    expect(wrapper.text()).toContain('executor')
  })

  it('renders eval agent messages with proof and memory handoff metadata', () => {
    const wrapper = mount(AgentMessageCard, {
      props: {
        message: {
          id: 'eval-1',
          agent: 'evaluator',
          title: 'Post-memory eval',
          status: 'completed',
          model_role: 'review',
          summary: 'Eval score 0.88',
          message: 'Final response, proof, and memory capture are aligned.',
          from_agent: 'memory',
          to_agent: 'system',
          artifacts: {
            evaluation: {
              score: 0.88,
              verdict: 'pass',
              recommendation: 'Keep the current memory template.',
              proof_summary: {
                files_read: 1,
                files_changed: 1,
                tests_run: 1,
              },
            },
            proof_files: ['app/Http/Controllers/UserController.php'],
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Post-memory eval')
    expect(wrapper.text()).toContain('0.88')
    expect(wrapper.text()).toContain('memory')
    expect(wrapper.text()).toContain('system')
    expect(wrapper.text()).toContain('UserController.php')
  })

  it('renders change tracking with fallback diff text', () => {
    const wrapper = mount(ChangeTrackerPanel, {
      props: {
        filesRead: [{ path: 'app/Foo.php', reason: 'Inspect behavior' }],
        filesChanged: [{ path: 'app/Foo.php', change_type: 'modified', summary: 'Changed validation' }],
        commandsRun: [{ command: 'php artisan test', status: 'passed' }],
        testsRun: [{ name: 'FeatureTest', status: 'passed', summary: 'Passed' }],
      },
      global: {
        components: { FileDiffViewer, TestResultPanel },
      },
    })

    expect(wrapper.text()).toContain('app/Foo.php')
    expect(wrapper.text()).toContain('No diff captured')
    expect(wrapper.text()).toContain('php artisan test')
  })

  it('renders colored diff lines for modifications', () => {
    const wrapper = mount(FileDiffViewer, {
      props: {
        path: 'app/Foo.php',
        changeType: 'modified',
        diff: '--- app/Foo.php\n+++ app/Foo.php\n-old line\n+new line',
      },
    })

    expect(wrapper.find('.text-emerald-300').exists()).toBe(true)
    expect(wrapper.find('.text-rose-300').exists()).toBe(true)
    expect(wrapper.text()).toContain('new line')
    expect(wrapper.text()).toContain('old line')
  })

  it('switches the landing conversation between chat and agent process lanes', async () => {
    const wrapper = mount(LandingConversationTabs, {
      props: {
        modelValue: 'chat',
        turns: [
          { id: 'turn-1', role: 'user', content: 'Fix the bug', createdAt: 1779667200000 },
          { id: 'turn-2', role: 'assistant', content: 'The bug is fixed.', createdAt: 1779667260000 },
        ],
        agentMessages: [
          {
            id: 'agent-1',
            agent: 'executor',
            title: 'Executor',
            status: 'completed',
            summary: 'Changed one file.',
          },
        ],
        handoffNodes: [
          { agent: 'orchestrator', label: 'Orchestrator', status: 'completed' },
          { agent: 'executor', label: 'Executor', status: 'completed' },
        ],
      },
      global: {
        stubs: {
          NuxtLink: { template: '<a><slot /></a>' },
          ChatTurnBubble: { props: ['turn'], template: '<article>{{ turn.role }}: {{ turn.content }}</article>' },
          AgentHandoffFlow: { props: ['nodes'], template: '<section>workflow {{ nodes.length }}</section>' },
          AgentMessageCard: { props: ['message'], template: '<article>{{ message.title }} {{ message.summary }}</article>' },
        },
      },
    })

    expect(wrapper.get('[data-testid="chat-thread-scroll"]').text()).toContain('Fix the bug')
    expect(wrapper.find('[data-testid="agent-process-scroll"]').exists()).toBe(false)

    const processButton = wrapper.findAll('button').find(button => button.text().includes('Agent Process'))
    expect(processButton).toBeTruthy()
    await processButton!.trigger('click')
    await wrapper.setProps({ modelValue: 'process' })
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['process'])
    expect(wrapper.get('[data-testid="agent-process-scroll"]').text()).toContain('workflow 2')
    expect(wrapper.get('[data-testid="agent-process-scroll"]').text()).toContain('Changed one file.')
    expect(wrapper.find('[data-testid="chat-thread-scroll"]').exists()).toBe(false)
  })

  it('renders new file with green lines from after content', () => {
    const wrapper = mount(FileDiffViewer, {
      props: {
        path: 'src/Pricing.vue',
        changeType: 'created',
        after: 'export default {}\n',
      },
    })

    expect(wrapper.text()).toContain('New file')
    expect(wrapper.find('.text-emerald-300').exists()).toBe(true)
    expect(wrapper.text()).toContain('export default {}')
  })

  it('renders audit findings and final result summary', () => {
    const audit = mount(AuditFindingsPanel, {
      props: {
        status: 'needs_revision',
        findings: [{ id: 'audit-1', severity: 'medium', category: 'tests', title: 'Missing test', suggested_fix: 'Add coverage' }],
      },
      global: {
        components: { UiEmptyState },
      },
    })
    const final = mount(FinalResultPanel, {
      props: {
        result: {
          status: 'Completed',
          summary: 'Safe to merge after checks.',
          filesChanged: ['app/Foo.php'],
          checksRun: ['php artisan test'],
          auditResult: 'pass_with_notes',
          remainingRisks: [{ issue: 'Full suite not run', severity: 'medium' }],
          nextStep: 'Run full suite',
          nextPrompt: 'Run php artisan test for app/Foo.php and report any failures.',
        },
      },
      global: {
        components: { RiskItemList },
      },
    })

    expect(audit.text()).toContain('Missing test')
    expect(audit.text()).toContain('Add coverage')
    expect(final.text()).toContain('Safe to merge')
    expect(final.text()).toContain('Run full suite')
    expect(final.text()).toContain('Run php artisan test for app/Foo.php')
    expect(final.find('[data-testid="final-next-prompt-copy"]').exists()).toBe(true)
  })
})
