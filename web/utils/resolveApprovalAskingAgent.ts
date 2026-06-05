import type { Approval } from '~/types/api'
import { isApprovalPauseEvent } from '~/utils/approvalStream'

export function resolveAskingAgent(
  approval: Approval | null,
  events: Array<Record<string, unknown>>,
): string {
  const fromEvidence = approval?.evidence?.asking_agent
  if (typeof fromEvidence === 'string' && fromEvidence.trim() !== '') {
    return fromEvidence.trim()
  }

  for (let i = events.length - 1; i >= 0; i--) {
    const evt = events[i]
    if (!isApprovalPauseEvent(evt)) continue
    const agent = evt.agent
    if (typeof agent === 'string' && agent.trim() !== '') {
      return agent.trim()
    }
  }

  return 'executor'
}
