import { describe, expect, it } from 'vitest'
import { useRunArtifacts } from '../composables/useRunArtifacts'

describe('useRunArtifacts', () => {
  it('normalizes live SSE events into agent workflow artifacts', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        model_role: 'reasoning',
        model: 'kimi-k2.6',
        summary: 'Planner created checklist.',
        artifacts: {
          plan: { task_summary: 'Improve controller' },
          checklist: [
            { id: 'plan-1', title: 'Inspect files', owner: 'executor', status: 'completed' },
          ],
        },
        latency_ms: 123,
      },
      {
        type: 'executor_step_done',
        agent: 'executor',
        status: 'success',
        model_role: 'coding',
        artifacts: {
          files_read: [{ path: 'app/Http/Controllers/UserController.php', reason: 'Inspect flow' }],
          files_changed: [{ path: 'app/Http/Controllers/UserController.php', change_type: 'modified', summary: 'Added policy check' }],
          commands_run: [{ command: 'php artisan test --filter=UserControllerTest', status: 'passed' }],
          tests_run: [{ name: 'UserControllerTest', status: 'passed', summary: 'Controller checks passed' }],
        },
      },
      {
        type: 'auditor_done',
        agent: 'auditor',
        status: 'needs_revision',
        model_role: 'review',
        artifacts: {
          audit_findings: [{ id: 'audit-1', severity: 'medium', category: 'tests', title: 'Add auth test' }],
        },
      },
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        output: '[BOSSKUAI]\n\n## Status\nCompleted',
      },
    ])

    expect(normalized.agentMessages).toHaveLength(4)
    expect(normalized.agentMessages[0].agent).toBe('orchestrator')
    expect(normalized.handoffNodes.map(node => node.agent)).toEqual([
      'orchestrator',
      'executor',
      'auditor',
      'executor',
      'final-reviewer',
    ])
    expect(normalized.checklist[0].title).toBe('Inspect files')
    expect(normalized.filesRead[0].path).toContain('UserController')
    expect(normalized.filesChanged[0].summary).toBe('Added policy check')

    const withAfter = useRunArtifacts([
      {
        type: 'executor_step_done',
        agent: 'executor',
        artifacts: {
          files_changed: [{
            path: 'src/New.vue',
            change_type: 'created',
            after: 'const x = 1',
          }],
        },
      },
    ])
    expect(withAfter.filesChanged[0].after).toBe('const x = 1')
    expect(normalized.commandsRun[0].command).toContain('php artisan test')
    expect(normalized.testsRun[0].name).toBe('UserControllerTest')
    expect(normalized.auditFindings[0].title).toBe('Add auth test')
    expect(normalized.finalResult.status).toBe('Completed')
  })

  it('extracts role models from model_router_done and agent steps', () => {
    const normalized = useRunArtifacts([
      {
        type: 'model_router_done',
        agent: 'router',
        status: 'success',
        models: {
          router: 'kimi-k2.6:cloud',
          orchestrator: 'kimi-k2.6:cloud',
          executor: 'qwen3-coder-next:cloud',
          auditor: 'deepseek-v4-pro:cloud',
        },
      },
      {
        type: 'planner_done',
        agent: 'orchestrator',
        model_role: 'reasoning',
        model: 'kimi-k2.6:cloud',
        status: 'success',
      },
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        models: {
          router: 'kimi-k2.6:cloud',
          orchestrator: 'kimi-k2.6:cloud',
          executor: 'qwen3-coder-next:cloud',
          auditor: 'deepseek-v4-pro:cloud',
        },
        output: 'Done',
      },
    ])

    expect(normalized.routingSummary.reasoningModel).toBe('kimi-k2.6:cloud')
    expect(normalized.routingSummary.codingModel).toBe('qwen3-coder-next:cloud')
    expect(normalized.routingSummary.reviewModel).toBe('deepseek-v4-pro:cloud')
    expect(normalized.routingSummary.fastModel).toBe('kimi-k2.6:cloud')
  })

  it('parses next prompt section from final output', () => {
    const normalized = useRunArtifacts([
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        output: [
          '[BOSSKUAI]',
          '## Status',
          'Completed',
          '## Next recommended step',
          'Run the relevant test suite before merge.',
          '## Next prompt',
          'Read and verify the changes in hello-world.txt. Confirm each file matches the intended outcome, then run the project test suite and report pass/fail with any errors.',
        ].join('\n'),
      },
    ])

    expect(normalized.finalResult.nextStep).toBe('Run the relevant test suite before merge.')
    expect(normalized.finalResult.nextPrompt).toContain('hello-world.txt')
    expect(normalized.finalResult.nextPrompt).toContain('test suite')
  })

  it('falls back next prompt to next step when section missing', () => {
    const normalized = useRunArtifacts([
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        output: [
          '## Next recommended step',
          'Run full suite locally',
        ].join('\n'),
      },
    ])

    expect(normalized.finalResult.nextPrompt).toBe('Run full suite locally')
  })

  it('parses JSON remaining risks from final output', () => {
    const normalized = useRunArtifacts([
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        output: [
          '[BOSSKUAI]',
          '## Status',
          'Completed',
          '## Remaining risks',
          '- {"issue":"Hardcoded URL","severity":"low","location":"config/services.php","description":"Use env var."}',
        ].join('\n'),
      },
    ])

    expect(normalized.finalResult.remainingRisks).toHaveLength(1)
    expect(normalized.finalResult.remainingRisks[0].issue).toBe('Hardcoded URL')
    expect(normalized.finalResult.remainingRisks[0].location).toBe('config/services.php')
  })

  it('humanizes router step output instead of raw JSON blob', () => {
    const normalized = useRunArtifacts([
      {
        type: 'model_router_done',
        agent: 'router',
        status: 'success',
        output: JSON.stringify({
          primary_skill: { name: 'bosskuai-codebase-analysis', reason: 'Matched audit keywords.' },
          secondary_skills: [],
          rules: [],
          playbooks: [],
          checklists: [],
        }),
        latency_ms: 48,
      },
    ])

    expect(normalized.agentMessages[0].summary).toContain('bosskuai-codebase-analysis')
    expect(normalized.agentMessages[0].router?.primarySkill).toBe('bosskuai-codebase-analysis')
    expect(String(normalized.agentMessages[0].message ?? '')).not.toContain('"_scores"')
  })

  it('normalizes saved run steps with metadata artifacts', () => {
    const normalized = useRunArtifacts([
      {
        id: 'step-1',
        type: 'planner',
        status: 'success',
        model: 'kimi-k2.6',
        latency_ms: 22,
        metadata: {
          agent: 'orchestrator',
          model_role: 'reasoning',
          summary: 'Plan ready',
          artifacts: {
            checklist: [{ id: 'plan-1', title: 'Audit result', owner: 'auditor', status: 'pending' }],
          },
        },
      },
      {
        id: 'step-2',
        type: 'auditor',
        output: JSON.stringify({
          status: 'pass_with_notes',
          findings: [{ id: 'audit-1', severity: 'low', title: 'Run full suite' }],
        }),
      },
    ])

    expect(normalized.agentMessages[0].title).toBe('Orchestrator')
    expect(normalized.checklist[0].owner).toBe('auditor')
    expect(normalized.auditFindings[0].severity).toBe('low')
  })
})
