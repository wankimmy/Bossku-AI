export type SseEvent = Record<string, unknown> & {
  run_id?: string
  type?: string
  status?: string
}

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
      error.value = 'Connection to the run stream was interrupted. If Ollama or the API is down, check docker compose services.'
      stop()
    }
  }

  onUnmounted(stop)

  return { events, running, error, start, stop }
}
