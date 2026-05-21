import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgentHandoffFlow from '../components/AgentHandoffFlow.vue'
import PlanChecklist from '../components/PlanChecklist.vue'
import ChangeTrackerPanel from '../components/ChangeTrackerPanel.vue'
import AuditFindingsPanel from '../components/AuditFindingsPanel.vue'
import FinalResultPanel from '../components/FinalResultPanel.vue'
import RiskItemList from '../components/RiskItemList.vue'
import FileDiffViewer from '../components/FileDiffViewer.vue'
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
  })
})
