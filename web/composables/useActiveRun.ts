import { loadActiveRunBinding } from '~/utils/activeRunStorage'
import { isAwaitingApprovals } from '~/utils/approvalStream'
import { isAwaitingClarification } from '~/utils/clarificationStream'
import { isRunStatusTerminal } from '~/utils/runStreamTerminal'

export function useActiveRun() {
  const {
    running,
    polling,
    activeRunId,
    boundConvId,
    events,
  } = useRunStream()

  const binding = computed(() => loadActiveRunBinding())

  const conversationId = computed(() =>
    boundConvId.value ?? binding.value?.convId ?? null,
  )

  const runId = computed(() =>
    activeRunId.value ?? binding.value?.runId ?? null,
  )

  const status = computed((): string | null => {
    if (!running.value && !polling.value) {
      const id = runId.value
      if (!id) return null
      const last = events.value.at(-1)
      if (last && isAwaitingClarification(events.value)) {
        return 'awaiting clarification'
      }
      if (last && isAwaitingApprovals(events.value)) {
        return 'awaiting approvals'
      }
      return null
    }

    if (isAwaitingClarification(events.value)) {
      return 'awaiting clarification'
    }
    if (isAwaitingApprovals(events.value)) {
      return 'awaiting approvals'
    }
    if (polling.value) {
      return 'running (reconnecting)'
    }
    return 'running'
  })

  const isActive = computed(() =>
    Boolean(status.value) || running.value || polling.value,
  )

  const resumeUrl = computed(() => {
    const conv = conversationId.value
    if (!conv) return '/'
    return { path: '/', query: { conv } }
  })

  return {
    status,
    runId,
    conversationId,
    isActive,
    resumeUrl,
    running,
    polling,
  }
}

export function shouldResumePolling(runStatus: string, events: { type?: string }[]): boolean {
  if (isRunStatusTerminal(runStatus)) return false
  if (isAwaitingClarification(events)) return true
  if (isAwaitingApprovals(events)) return true
  return runStatus === 'running' || runStatus === 'awaiting_input'
}
