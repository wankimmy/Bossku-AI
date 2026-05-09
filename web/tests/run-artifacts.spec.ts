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
    expect(normalized.commandsRun[0].command).toContain('php artisan test')
    expect(normalized.testsRun[0].name).toBe('UserControllerTest')
    expect(normalized.auditFindings[0].title).toBe('Add auth test')
    expect(normalized.finalResult.status).toBe('Completed')
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
