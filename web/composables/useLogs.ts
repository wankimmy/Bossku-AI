import type { LogEntry } from '~/types/api'
import { apiUrl } from '~/composables/useApiBase'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'

const MAX_ENTRIES = 200

export function useLogs() {
  // useState so entries persist while AppShell stays mounted across route changes
  const logs = useState<LogEntry[]>('bossku-logs', () => [])
  let abort: AbortController | null = null

  async function connect() {
    if (!import.meta.client) return
    abort?.abort()
    abort = new AbortController()

    try {
      const res = await fetch(apiUrl('/logs/stream'), {
        headers: apiAuthHeaders(),
        signal: abort.signal,
      })

      if (!res.ok || !res.body) {
        setTimeout(connect, 5_000)
        return
      }

      const reader = res.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() ?? ''
        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          try {
            const entry = JSON.parse(line.slice(6)) as LogEntry
            logs.value = [...logs.value.slice(-(MAX_ENTRIES - 1)), entry]
          }
          catch { /* ignore malformed events */ }
        }
      }
    }
    catch (err: unknown) {
      if (err instanceof Error && err.name === 'AbortError') return
    }

    // Reconnect 2s after stream ends (server sends 60s streams then closes)
    setTimeout(connect, 2_000)
  }

  onMounted(() => { void connect() })
  onUnmounted(() => { abort?.abort() })

  return { logs }
}
