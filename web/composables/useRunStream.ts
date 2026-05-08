export type SseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
}

/** SSE event types that mean the run ended normally (do not show generic "interrupted"). */
const TERMINAL_EVENT_TYPES = new Set([
  'run_completed',
  'run_failed',
  'planner_failed',
])

/** Run via SSE GET /api/runs/stream — returns cleanup fn */
export function useRunStream() {
  const events = ref<SseEvent[]>([])
  const running = ref(false)
  const error = ref<string | null>(null)
  let src: EventSource | null = null

  function stop() {
    src?.close()
    src = null
    running.value = false
  }

  function start(prompt: string) {
    stop()
    error.value = null
    events.value = []
    running.value = true
    const base = useApiBase()
    const url = `${base}/api/runs/stream?prompt=${encodeURIComponent(prompt)}`
    src = new EventSource(url)
    src.onmessage = (ev) => {
      try {
        events.value.push(JSON.parse(ev.data) as SseEvent)
      }
      catch {
        //
      }
    }
    src.onerror = () => {
      const lastType = events.value.at(-1)?.type
      const reachedTerminal = lastType !== undefined && TERMINAL_EVENT_TYPES.has(String(lastType))

      if (!reachedTerminal) {
        if (events.value.length === 0) {
          error.value
            = 'Stream failed to start: the API closed before any events (often HTTP 500). '
              + 'Check `docker compose logs -f backend` — bootstrap/cache and storage must be writable. '
              + 'Rebuild: `docker compose build backend && docker compose up -d backend`.'
        }
        else {
          error.value
            = 'Connection to the run stream was interrupted mid-run. If Ollama or the API crashed, check `docker compose logs -f backend nginx`.'
        }
      }
      stop()
    }
  }

  onUnmounted(stop)

  return { events, running, error, start, stop }
}
