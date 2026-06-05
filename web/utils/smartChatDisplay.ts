import type { RoutingSummary } from '~/types/bossku'

const SHORT_CHAT_WORKFLOWS = new Set(['direct_answer', 'writer_only'])
const AGENT_PANEL_ROLES = new Set(['executor', 'auditor', 'security-auditor', 'final-reviewer'])

export function isShortChatWorkflow(routing?: Pick<RoutingSummary, 'workflow'> | null): boolean {
  return SHORT_CHAT_WORKFLOWS.has(String(routing?.workflow ?? ''))
}

export function shouldFocusAgentWorkflow(routing?: Pick<RoutingSummary, 'workflow' | 'pipelineAgents'> | null): boolean {
  if (!routing || isShortChatWorkflow(routing)) return false

  const pipelineAgents = routing.pipelineAgents ?? []
  if (pipelineAgents.some(agent => AGENT_PANEL_ROLES.has(agent))) {
    return true
  }

  const workflow = String(routing.workflow ?? '')
  return workflow.includes('executor')
    || /_auditor(?:_|$)/.test(workflow)
    || workflow.includes('security')
    || workflow.includes('final_reviewer')
}

export function shouldShowRunSummaryCard(
  routing: Pick<RoutingSummary, 'workflow'> | null | undefined,
  hasFinalOutput: boolean,
): boolean {
  return hasFinalOutput && !isShortChatWorkflow(routing)
}
