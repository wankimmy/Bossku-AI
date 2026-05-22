import type { ClarificationAnswer, ClarificationRequest } from '~/types/clarification'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'
import { isAwaitingApprovals } from '~/utils/approvalStream'
import { buildClarificationRequest, isAwaitingClarification } from '~/utils/clarificationStream'

export type { ClarificationAnswer, ClarificationQuestion, ClarificationRequest } from '~/types/clarification'

export type SseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
}

export type ConversationTurn = {
  role: 'user' | 'assistant'
  content: string
}

/** SSE event types that mean the run ended normally (do not show generic "interrupted"). */
const TERMINAL_EVENT_TYPES = new Set([
  'run_completed',
  'run_failed',
  'planner_failed',
])

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

/** Run via POST /api/runs/stream (SSE) — supports conversation history and clarification continue. */
export function useRunStream() {
  const events = ref<SseEvent[]>([])
  const running = ref(false)
  const error = ref<string | null>(null)
  const activeRunId = ref<string | null>(null)
  let abort: AbortController | null = null

  const awaitingClarification = computed(() => isAwaitingClarification(events.value))

  const clarificationRequest = computed((): ClarificationRequest | null =>
    buildClarificationRequest(events.value, activeRunId.value),
  )

  function trackEvent(evt: SseEvent) {
    if (evt.run_id) activeRunId.value = String(evt.run_id)
    events.value.push(evt)
  }

  function stop() {
    abort?.abort()
    abort = null
    running.value = false
  }

  async function start(
    prompt: string,
    options?: { conversation?: ConversationTurn[]; appendEvents?: boolean },
  ) {
    stop()
    error.value = null
    if (!options?.appendEvents) {
      events.value = []
      activeRunId.value = null
    }
    running.value = true
    const base = useApiBase()
    abort = new AbortController()

    try {
      const res = await fetch(`${base}/api/runs/stream`, {
        method: 'POST',
        headers: {
          ...apiAuthHeaders(),
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        },
        body: JSON.stringify({
          prompt,
          conversation: options?.conversation ?? [],
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
      if (e instanceof DOMException && e.name === 'AbortError') {
        running.value = false
        return
      }

      const lastType = events.value.at(-1)?.type
      const reachedTerminal = lastType !== undefined && TERMINAL_EVENT_TYPES.has(String(lastType))
      const pausedForClarification = isAwaitingClarification(events.value)
      const pausedForApprovals = isAwaitingApprovals(events.value)

      if (!reachedTerminal && !pausedForClarification && !pausedForApprovals) {
        if (events.value.length === 0) {
          error.value
            = 'Stream failed to start: the API closed before any events (often HTTP 500). '
              + 'Check `docker compose logs -f backend` — bootstrap/cache and storage must be writable. '
              + 'Rebuild: `docker compose build backend && docker compose up -d backend`.'
        }
        else {
          error.value
            = e instanceof Error
              ? e.message
              : 'Connection to the run stream was interrupted mid-run. If Ollama or the API crashed, check `docker compose logs -f backend nginx`.'
        }
      }
    }
    finally {
      running.value = false
      abort = null
    }
  }

  async function continueAfterApprovals(runId: string) {
    stop()
    error.value = null
    running.value = true
    activeRunId.value = runId
    const base = useApiBase()
    abort = new AbortController()

    try {
      const res = await fetch(`${base}/api/runs/${runId}/continue-approvals/stream`, {
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
      if (e instanceof DOMException && e.name === 'AbortError') {
        running.value = false
        return
      }
      const lastType = events.value.at(-1)?.type
      const reachedTerminal = lastType !== undefined && TERMINAL_EVENT_TYPES.has(String(lastType))
      const paused = isAwaitingClarification(events.value) || isAwaitingApprovals(events.value)
      if (!reachedTerminal && !paused) {
        error.value = e instanceof Error ? e.message : 'Continue after approvals was interrupted.'
      }
    }
    finally {
      running.value = false
      abort = null
    }
  }

  async function continueRun(runId: string, answers: ClarificationAnswer[]) {
    stop()
    error.value = null
    running.value = true
    activeRunId.value = runId
    const base = useApiBase()
    abort = new AbortController()

    try {
      const res = await fetch(`${base}/api/runs/${runId}/continue/stream`, {
        method: 'POST',
        headers: {
          ...apiAuthHeaders(),
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        },
        body: JSON.stringify({ answers }),
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
      if (e instanceof DOMException && e.name === 'AbortError') {
        running.value = false
        return
      }

      const lastType = events.value.at(-1)?.type
      const reachedTerminal = lastType !== undefined && TERMINAL_EVENT_TYPES.has(String(lastType))
      const pausedForClarification = isAwaitingClarification(events.value)
      const pausedForApprovals = isAwaitingApprovals(events.value)

      if (!reachedTerminal && !pausedForClarification && !pausedForApprovals) {
        error.value = e instanceof Error ? e.message : 'Continue stream was interrupted.'
      }
    }
    finally {
      running.value = false
      abort = null
    }
  }

  onUnmounted(stop)

  return {
    events,
    running,
    error,
    activeRunId,
    awaitingClarification,
    clarificationRequest,
    start,
    continueRun,
    continueAfterApprovals,
    stop,
  }
}
