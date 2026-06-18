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
      {
        type: 'post_memory_eval_done',
        agent: 'evaluator',
        status: 'success',
        from_agent: 'memory',
        to_agent: 'system',
        model_role: 'review',
        artifacts: {
          evaluation: {
            score: 0.88,
            verdict: 'pass',
            summary: 'Final response, proof, and memory capture are aligned.',
            recommendation: 'Keep the current memory template.',
            dimensions: [
              { id: 'final_response', label: 'Final response', score: 0.9 },
            ],
          },
        },
      },
    ])

    expect(normalized.agentMessages).toHaveLength(5)
    expect(normalized.agentMessages[0].agent).toBe('orchestrator')
    expect(normalized.agentMessages[4].agent).toBe('evaluator')
    expect(normalized.agentMessages[4].summary).toContain('0.88')
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

  it('builds pipeline path and skipped agents from routing artifacts', () => {
    const normalized = useRunArtifacts([
      {
        type: 'model_router_done',
        agent: 'router',
        status: 'success',
        routing: {
          workflow: 'orchestrator_executor',
          needs_auditor: false,
        },
        artifacts: {
          routing_decision: { workflow: 'orchestrator_executor', needs_auditor: false },
          pipeline_agents: ['orchestrator', 'executor'],
          skipped_agents: ['auditor', 'security-auditor', 'final-reviewer'],
        },
      },
      {
        type: 'agents_skipped',
        agent: 'orchestrator',
        status: 'success',
        artifacts: { skipped_agents: ['auditor'] },
      },
    ])

    expect(normalized.routingSummary.workflow).toBe('orchestrator_executor')
    expect(normalized.routingSummary.pipelinePath).toBe(
      'Route: orchestrator → executor (auditor, security-auditor, final-reviewer skipped)',
    )
  })

  it('derives live plan checklist status from executor events', () => {
    const running = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Implement requested change', owner: 'executor', status: 'pending' },
            { id: 'plan-2', title: 'Review implementation', owner: 'auditor', status: 'pending' },
          ],
        },
      },
      {
        type: 'executor_step_started',
        agent: 'executor',
        status: 'running',
      },
    ])

    expect(running.checklist[0].status).toBe('running')
    expect(running.checklist[1].status).toBe('pending')

    const completed = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Implement requested change', owner: 'executor', status: 'pending' },
            { id: 'plan-2', title: 'Review implementation', owner: 'auditor', status: 'pending' },
          ],
        },
      },
      {
        type: 'executor_step_done',
        agent: 'executor',
        status: 'success',
      },
      {
        type: 'auditor_done',
        agent: 'auditor',
        status: 'success',
      },
    ])

    expect(completed.checklist[0].status).toBe('completed')
    expect(completed.checklist[1].status).toBe('completed')
  })

  it('uses reconciled checklist from checklist_verdict instead of marking disputed items completed', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Scaffold project', owner: 'executor', status: 'pending' },
            { id: 'plan-2', title: 'Build 3D scene', owner: 'executor', status: 'pending' },
          ],
        },
      },
      {
        type: 'executor_step_done',
        agent: 'executor',
        status: 'success',
      },
      {
        type: 'auditor_done',
        agent: 'auditor',
        status: 'success',
      },
      {
        type: 'checklist_verdict',
        agent: 'auditor',
        status: 'warning',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Scaffold project', owner: 'executor', status: 'disputed' },
            { id: 'plan-2', title: 'Build 3D scene', owner: 'executor', status: 'unverifiable' },
          ],
        },
      },
    ])

    expect(normalized.checklist[0].status).toBe('disputed')
    expect(normalized.checklist[1].status).toBe('unverifiable')
  })

  it('marks executor checklist awaiting input during user-local command pauses', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Scaffold Nuxt project with dependencies', owner: 'executor', status: 'pending' },
            { id: 'plan-2', title: 'Review implementation', owner: 'auditor', status: 'pending' },
          ],
        },
      },
      {
        type: 'executor_step_started',
        agent: 'executor',
        status: 'running',
      },
      {
        type: 'clarification_requested',
        agent: 'executor',
        status: 'awaiting_input',
        stage: 'user_local_commands',
        origin: 'user_local_commands',
        artifacts: {
          proof: {
            checklist_status: [
              { id: 'plan-1', status: 'awaiting_input', notes: 'Run npm install locally and paste the output.' },
            ],
            commands_run: [{ command: 'npm install', reason: 'exit 127' }],
            blockers: ['npm install: exit 127'],
          },
        },
      },
    ])

    expect(normalized.checklist[0].title).toBe('Scaffold Nuxt project with dependencies')
    expect(normalized.checklist[0].owner).toBe('executor')
    expect(normalized.checklist[0].status).toBe('awaiting_input')
    expect(normalized.checklist[1].status).toBe('pending')
  })

  it('keeps explicit partial executor checklist status through run completion', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          checklist: [
            { id: 'plan-1', title: 'Build GarageScene 3D environment', owner: 'executor', status: 'pending' },
          ],
        },
      },
      {
        type: 'executor_step_done',
        agent: 'executor',
        status: 'success',
        artifacts: {
          checklist_status: [
            { id: 'plan-1', status: 'partial', notes: 'Dependencies are not installed yet.' },
          ],
        },
      },
      {
        type: 'run_completed',
        agent: 'final-reviewer',
        status: 'success',
        output: 'Waiting for local command output.',
      },
    ])

    expect(normalized.checklist[0].title).toBe('Build GarageScene 3D environment')
    expect(normalized.checklist[0].owner).toBe('executor')
    expect(normalized.checklist[0].status).toBe('partial')
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

  it('normalizes specialist agent handoff events as first-class messages and handoff nodes', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
      },
      {
        type: 'specialist_agent_done',
        agent: 'checkout-specialist',
        status: 'success',
        model_role: 'reasoning',
        summary: 'Checkout specialist prepared execution strategy.',
        artifacts: {
          specialist_agent: {
            id: '11111111-1111-4111-8111-111111111111',
            role_slug: 'checkout-specialist',
            display_name: 'Checkout Specialist',
          },
          specialist_handoff: {
            summary: 'Checkout fee risk found.',
            handoff_to_executor: 'Read checkout totals and fee formatting before editing.',
          },
        },
      },
      {
        type: 'executor_step_started',
        agent: 'executor',
        status: 'running',
      },
    ])

    expect(normalized.agentMessages[1].agent).toBe('checkout-specialist')
    expect(normalized.agentMessages[1].title).toBe('Checkout Specialist')
    expect(normalized.handoffNodes.map(node => node.agent)).toEqual([
      'orchestrator',
      'checkout-specialist',
      'executor',
      'auditor',
      'executor',
      'final-reviewer',
    ])
    expect(normalized.handoffNodes[1].label).toBe('Checkout Specialist')
  })

  it('builds cursor-style plan overview from planner_done artifacts', () => {
    const normalized = useRunArtifacts([
      {
        type: 'planner_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          plan: {
            goal: 'Add file attachments to chat',
            task_summary: 'Chat attachments',
            key_design_decisions: ['Ingest files to text at run entry'],
            flow_diagram: 'flowchart TD\n  upload --> ingest',
            flow_steps: ['Upload', 'Ingest', 'Run'],
            constraints: ['Keep executor JSON-only'],
            risk_notes: ['Vision model may be unavailable'],
            council_review: {
              status: 'completed',
              voices: [
                { id: 'skeptic', label: 'Skeptic', position: 'Clarify attachment size limits.' },
              ],
              consensus: 'Reuse existing run context.',
              strongest_dissent: 'Clarify attachment size limits.',
              recommended_adjustments: ['Confirm file size scope before execution.'],
              stop_conditions: ['Pause when token budget is crossed.'],
            },
            staff_council: {
              status: 'completed',
              voices: [
                { role_slug: 'tech-lead', display_name: 'Tech Lead', position: 'Keep implementation bounded.' },
              ],
              staff_recommendations: ['Create approved work issues from plan items.'],
              issue_breakdown: [
                { plan_item_id: 'plan-1', title: 'Backend upload API', assignee_role_slug: 'tech-lead', priority: 'high' },
              ],
              stop_conditions: ['Wait for CEO approval before starting more work.'],
            },
            checklist: [
              { id: 'plan-1', title: 'Backend upload API', owner: 'executor', status: 'pending' },
            ],
          },
          checklist: [
            { id: 'plan-1', title: 'Backend upload API', owner: 'executor', status: 'pending' },
          ],
        },
      },
    ])

    expect(normalized.plan?.goal).toBe('Add file attachments to chat')
    expect(normalized.plan?.keyDesignDecisions).toEqual(['Ingest files to text at run entry'])
    expect(normalized.plan?.flowDiagram).toContain('flowchart TD')
    expect(normalized.plan?.flowSteps).toEqual(['Upload', 'Ingest', 'Run'])
    expect(normalized.plan?.notes).toEqual(['Keep executor JSON-only'])
    expect(normalized.plan?.risks).toEqual(['Vision model may be unavailable'])
    expect(normalized.plan?.councilReview?.strongest_dissent).toBe('Clarify attachment size limits.')
    expect(normalized.plan?.councilReview?.stop_conditions).toContain('Pause when token budget is crossed.')
    expect(normalized.plan?.staffCouncil?.voices[0].role_slug).toBe('tech-lead')
    expect(normalized.plan?.staffCouncil?.issue_breakdown[0].assignee_role_slug).toBe('tech-lead')
    expect(normalized.plan?.todos[0].title).toBe('Backend upload API')
  })

  it('keeps council and planner review events visible in agent conversation', () => {
    const normalized = useRunArtifacts([
      {
        type: 'council_review_done',
        agent: 'orchestrator',
        status: 'success',
        summary: 'Council review added dissent and stop conditions.',
        artifacts: {
          council_review: {
            status: 'completed',
            strongest_dissent: 'Limit the first implementation slice.',
          },
        },
      },
      {
        type: 'staff_council_done',
        agent: 'orchestrator',
        status: 'success',
        artifacts: {
          staff_council: {
            status: 'completed',
            voices: [
              { role_slug: 'project-manager', display_name: 'Project Manager', position: 'Convert approved plan items into issues.' },
            ],
            staff_recommendations: ['Wait for CEO approval before starting more work.'],
            issue_breakdown: [],
            stop_conditions: ['CEO approval gates follow-up execution.'],
          },
        },
      },
      {
        type: 'clarification_requested',
        agent: 'planner',
        status: 'awaiting_input',
        stage: 'planner_review',
        summary: 'Review the master plan before execution.',
        artifacts: {
          clarification: {
            stage: 'planner_review',
            from_agent: 'planner',
            questions: [],
            assumptions: [],
          },
        },
      },
    ])

    expect(normalized.agentMessages).toHaveLength(3)
    expect(normalized.agentMessages[0].summary).toContain('Council review')
    expect(normalized.agentMessages[1].summary).toContain('Staff council')
    expect(normalized.agentMessages[2].agent).toBe('planner')
    expect(normalized.agentMessages[2].summary).toBe('Review the master plan before execution.')
  })
})
