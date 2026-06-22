import { apiUrl } from '~/composables/useApiBase'
import type { ClarificationAnswer, ClarificationRequest } from '~/types/clarification'
import {
  clearActiveRunBinding,
  loadActiveRunBinding,
  saveActiveRunBinding,
  updateActiveRunLastSeq,
} from '~/utils/activeRunStorage'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'
import { isAwaitingApprovals } from '~/utils/approvalStream'
import { buildClarificationRequest, isAwaitingClarification } from '~/utils/clarificationStream'
import { isRunNotFoundError } from '~/utils/runStreamErrors'
import { isRunStatusTerminal, isTerminalStreamEvent } from '~/utils/runStreamTerminal'

export type { ClarificationAnswer, ClarificationQuestion, ClarificationRequest } from '~/types/clarification'

export type SseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
  seq?: number
}

export type ConversationTurn = {
  role: 'user' | 'assistant'
  content: string
}

type StreamEventsResponse = {
  run_id: string
  status: string
  events: SseEvent[]
  last_seq: number
}

const TERMINAL_EVENT_TYPES = new Set([
  'run_completed',
  'run_failed',
  'planner_failed',
])

let abort: AbortController | null = null
let pollTimer: ReturnType<typeof setInterval> | null = null
let pollInFlight = false

async function consumeSseStream(
  reader: ReadableStreamDefaultReader<Uint8Array>,
  onEvent: (evt: SseEvent) => void,
) {
  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    if (done) break
    buffer += decoder.decode(value, { stream: true })
    const chunks = buffer.split('\n')
    buffer = chunks.pop() ?? ''
    for (const line of chunks) {
      if (!line.startsWith('data: ')) continue
      try {
        onEvent(JSON.parse(line.slice(6)) as SseEvent)
      }
      catch {
        //
      }
    }
  }

  if (buffer.startsWith('data: ')) {
    try {
      onEvent(JSON.parse(buffer.slice(6)) as SseEvent)
    }
    catch {
      //
    }
  }
}

function stopPolling() {
  if (pollTimer !== null) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

/** App-global run stream — survives route changes; reconnects via polling after refresh. */
export function useRunStream() {
  const events = useState<SseEvent[]>('bossku-run-stream-events', () => [])
  const running = useState<boolean>('bossku-run-stream-running', () => false)
  const polling = useState<boolean>('bossku-run-stream-polling', () => false)
  const error = useState<string | null>('bossku-run-stream-error', () => null)
  const activeRunId = useState<string | null>('bossku-run-stream-active-run-id', () => null)
  const boundConvId = useState<string | null>('bossku-run-stream-bound-conv-id', () => null)
  const lastPolledSeq = useState<number>('bossku-run-stream-last-seq', () => 0)

  const awaitingClarification = computed(() => isAwaitingClarification(events.value))

  const clarificationRequest = computed((): ClarificationRequest | null =>
    buildClarificationRequest(events.value, activeRunId.value),
  )

  function bindConversation(convId: string) {
    boundConvId.value = convId
    const binding = loadActiveRunBinding()
    if (binding?.convId === convId && binding.runId) {
      activeRunId.value = binding.runId
      lastPolledSeq.value = binding.lastSeq
    }
  }

  function persistBinding() {
    const convId = boundConvId.value
    const runId = activeRunId.value
    if (!convId || !runId) return
    saveActiveRunBinding({
      convId,
      runId,
      lastSeq: lastPolledSeq.value,
    })
  }

  function clearBinding() {
    clearActiveRunBinding()
    boundConvId.value = null
  }

  /** Stop polling and drop stale run binding after the API reports the run is gone. */
  function abandonStaleRun() {
    stopPolling()
    running.value = false
    polling.value = false
    activeRunId.value = null
    clearBinding()
  }

  function trackEvent(evt: SseEvent) {
    if (evt.run_id) {
      activeRunId.value = String(evt.run_id)
    }
    if (typeof evt.seq === 'number') {
      const seq = evt.seq
      if (events.value.some(e => e.seq === seq)) {
        return
      }
      lastPolledSeq.value = Math.max(lastPolledSeq.value, seq)
      updateActiveRunLastSeq(lastPolledSeq.value)
    }
    events.value.push(evt)
    persistBinding()

    if (isTerminalStreamEvent(evt)) {
      running.value = false
      stopPolling()
      polling.value = false
      clearBinding()
    }
  }

  function mergePolledBatch(batch: SseEvent[], lastSeq: number) {
    for (const evt of batch) {
      trackEvent(evt)
    }
    if (lastSeq > lastPolledSeq.value) {
      lastPolledSeq.value = lastSeq
      updateActiveRunLastSeq(lastSeq)
    }
    persistBinding()
  }

  async function pollOnce(runId: string): Promise<boolean> {
    if (pollInFlight) return false
    pollInFlight = true
    try {
      const afterSeq = lastPolledSeq.value
      const res = await $fetch<StreamEventsResponse>(
        apiUrl(`/runs/${runId}/stream-events?after_seq=${afterSeq}`),
        { headers: apiAuthHeaders() },
      )
      if (res.events.length > 0) {
        mergePolledBatch(res.events, res.last_seq)
      }
      else if (res.last_seq > lastPolledSeq.value) {
        lastPolledSeq.value = res.last_seq
      }

      const lastEvt = events.value.at(-1)
      const terminalEvent = lastEvt !== undefined && isTerminalStreamEvent(lastEvt)
      const terminalStatus = isRunStatusTerminal(res.status)
      const paused = isAwaitingClarification(events.value) || isAwaitingApprovals(events.value)

      if (terminalEvent || (terminalStatus && !paused)) {
        running.value = false
        polling.value = false
        stopPolling()
        clearBinding()
        return true
      }

      if (paused) {
        running.value = false
        polling.value = false
        stopPolling()
        return true
      }

      return false
    }
    catch (e: unknown) {
      if (isRunNotFoundError(e)) {
        abandonStaleRun()
        return true
      }

      return false
    }
    finally {
      pollInFlight = false
    }
  }

  function attachPoll(runId: string, options?: { convId?: string; replaceEvents?: boolean }) {
    stopPolling()
    if (options?.convId) {
      bindConversation(options.convId)
    }
    activeRunId.value = runId
    if (options?.replaceEvents) {
      events.value = []
      lastPolledSeq.value = 0
    }
    const binding = loadActiveRunBinding()
    if (binding?.runId === runId && binding.lastSeq > lastPolledSeq.value) {
      lastPolledSeq.value = binding.lastSeq
    }
    polling.value = true
    running.value = true
    error.value = null
    persistBinding()

    void pollOnce(runId)
    pollTimer = setInterval(() => {
      void pollOnce(runId)
    }, 2000)
  }

  function stop() {
    abort?.abort()
    abort = null
    stopPolling()
    running.value = false
    polling.value = false
    clearBinding()
  }

  function handleStreamError(e: unknown, context: string) {
    if (e instanceof DOMException && e.name === 'AbortError') {
      if (!polling.value) {
        running.value = false
      }
      return
    }

    const lastType = events.value.at(-1)?.type
    const reachedTerminal = lastType !== undefined && TERMINAL_EVENT_TYPES.has(String(lastType))
    const pausedForClarification = isAwaitingClarification(events.value)
    const pausedForApprovals = isAwaitingApprovals(events.value)

    if (!reachedTerminal && !pausedForClarification && !pausedForApprovals) {
      if (isRunNotFoundError(e)) {
        abandonStaleRun()
        return
      }

      const runId = activeRunId.value
      if (runId) {
        attachPoll(runId)
        return
      }
      error.value = e instanceof Error ? e.message : `${context} was interrupted.`
      running.value = false
    }
  }

  async function start(
    prompt: string,
    options?: {
      conversation?: ConversationTurn[]
      appendEvents?: boolean
      convId?: string
      attachmentIds?: string[]
      continuationRunId?: string
    },
  ) {
    if (options?.convId) {
      bindConversation(options.convId)
    }
    abort?.abort()
    abort = null
    stopPolling()
    error.value = null
    if (!options?.appendEvents) {
      events.value = []
      activeRunId.value = null
      lastPolledSeq.value = 0
    }
    running.value = true
    polling.value = false
    abort = new AbortController()

    try {
      const res = await fetch(apiUrl('/runs/stream'), {
        method: 'POST',
        headers: {
          ...apiAuthHeaders(),
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        },
        body: JSON.stringify({
          prompt,
          conversation: options?.conversation ?? [],
          attachment_ids: options?.attachmentIds ?? [],
          continuation_run_id: options?.continuationRunId ?? undefined,
        }),
        signal: abort.signal,
      })

      if (!res.ok) {
        const text = await res.text().catch(() => '')
        throw new Error(text || `Stream request failed (${res.status})`)
      }

      const reader = res.body?.getReader()
      if (!reader) {
        throw new Error('No response body from run stream.')
      }

      await consumeSseStream(reader, trackEvent)
    }
    catch (e: unknown) {
      if (events.value.length === 0 && !(e instanceof DOMException && e.name === 'AbortError')) {
        error.value
          = 'Stream failed to start: the API closed before any events (often HTTP 500). '
            + 'Check `docker compose logs -f backend` — bootstrap/cache and storage must be writable. '
            + 'Rebuild: `docker compose build backend && docker compose up -d backend`.'
      }
      handleStreamError(e, 'Stream')
    }
    finally {
      abort = null
      if (!polling.value) {
        const paused = isAwaitingClarification(events.value) || isAwaitingApprovals(events.value)
        const lastEvt = events.value.at(-1)
        const terminal = lastEvt !== undefined && isTerminalStreamEvent(lastEvt)
        if (!terminal && !paused && activeRunId.value) {
          attachPoll(activeRunId.value)
        }
        else if (!polling.value) {
          running.value = false
        }
      }
    }
  }

  async function continueAfterApprovals(runId: string) {
    abort?.abort()
    abort = null
    stopPolling()
    error.value = null
    running.value = true
    polling.value = false
    activeRunId.value = runId
    abort = new AbortController()
    persistBinding()

    try {
      const res = await fetch(apiUrl(`/runs/${runId}/continue-approvals/stream`), {
        method: 'POST',
        headers: { ...apiAuthHeaders(), Accept: 'text/event-stream' },
        signal: abort.signal,
      })

      if (!res.ok) {
        const text = await res.text().catch(() => '')
        throw new Error(text || `Continue approvals failed (${res.status})`)
      }

      const reader = res.body?.getReader()
      if (!reader) throw new Error('No response body from continue-approvals stream.')

      await consumeSseStream(reader, trackEvent)
    }
    catch (e: unknown) {
      handleStreamError(e, 'Continue after approvals')
    }
    finally {
      abort = null
      if (!polling.value) {
        const paused = isAwaitingClarification(events.value) || isAwaitingApprovals(events.value)
        const lastEvt = events.value.at(-1)
        const terminal = lastEvt !== undefined && isTerminalStreamEvent(lastEvt)
        if (!terminal && !paused) {
          attachPoll(runId)
        }
        else {
          running.value = false
        }
      }
    }
  }

  async function continueRun(
    runId: string,
    answers: ClarificationAnswer[],
    reviewDecision: 'approve' | 'request_changes' = 'approve',
    codeReviewComment?: string,
  ) {
    abort?.abort()
    abort = null
    stopPolling()
    error.value = null
    running.value = true
    polling.value = false
    activeRunId.value = runId
    abort = new AbortController()
    persistBinding()

    try {
      const res = await fetch(apiUrl(`/runs/${runId}/continue/stream`), {
        method: 'POST',
        headers: {
          ...apiAuthHeaders(),
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        },
        body: JSON.stringify({
          answers,
          review_decision: reviewDecision,
          code_review_comment: codeReviewComment,
        }),
        signal: abort.signal,
      })

      if (!res.ok) {
        if (res.status === 409) {
          return
        }
        const text = await res.text().catch(() => '')
        throw new Error(text || `Continue stream failed (${res.status})`)
      }

      const reader = res.body?.getReader()
      if (!reader) {
        throw new Error('No response body from continue stream.')
      }

      await consumeSseStream(reader, trackEvent)
    }
    catch (e: unknown) {
      handleStreamError(e, 'Continue stream')
    }
    finally {
      abort = null
      if (!polling.value) {
        const paused = isAwaitingClarification(events.value) || isAwaitingApprovals(events.value)
        const lastEvt = events.value.at(-1)
        const terminal = lastEvt !== undefined && isTerminalStreamEvent(lastEvt)
        if (!terminal && !paused) {
          attachPoll(runId)
        }
        else {
          running.value = false
        }
      }
    }
  }

  return {
    events,
    running,
    polling,
    error,
    activeRunId,
    boundConvId,
    lastPolledSeq,
    awaitingClarification,
    clarificationRequest,
    bindConversation,
    attachPoll,
    abandonStaleRun,
    start,
    continueRun,
    continueAfterApprovals,
    stop,
  }
}
