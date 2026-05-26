import { loadActiveRunBinding } from '~/utils/activeRunStorage'
import { shouldResumePolling } from '~/composables/useActiveRun'
import { apiUrl } from '~/composables/useApiBase'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'

export default defineNuxtPlugin(() => {
  if (!import.meta.client) return

  const chat = useLandingChat()
  const stream = useRunStream()

  chat.hydrateFromStorage()

  const binding = loadActiveRunBinding()
  if (!binding?.runId) return

  if (stream.running.value || stream.polling.value) return

  const convId = binding.convId
  chat.selectConversation(convId)
  stream.bindConversation(convId)

  const saved = chat.getRunEvents(convId)
  if (saved.length > 0) {
    stream.events.value = [...saved]
  }

  void $fetch<{
    run_id: string
    status: string
    events: Record<string, unknown>[]
    last_seq: number
  }>(apiUrl(`/runs/${binding.runId}/stream-events?after_seq=${binding.lastSeq}`), {
    headers: apiAuthHeaders(),
  }).then((res) => {
    if (res.events.length > 0) {
      for (const evt of res.events) {
        const seq = typeof evt.seq === 'number' ? evt.seq : undefined
        if (seq !== undefined && stream.events.value.some(e => e.seq === seq)) {
          continue
        }
        stream.events.value.push(evt as typeof stream.events.value[0])
      }
      stream.lastPolledSeq.value = res.last_seq
    }

    if (shouldResumePolling(res.status, stream.events.value)) {
      stream.attachPoll(binding.runId, { convId })
    }
    else {
      chat.saveRunEvents(stream.events.value)
    }
  }).catch(() => {
    if (shouldResumePolling('running', stream.events.value)) {
      stream.attachPoll(binding.runId, { convId })
    }
  })
})
