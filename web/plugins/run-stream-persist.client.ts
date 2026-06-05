import { useLandingChat } from '~/composables/useLandingChat'
import { useRunStream } from '~/composables/useRunStream'

/** Throttled localStorage persist while runs stream/poll on any route (not only index). */
export default defineNuxtPlugin(() => {
  if (!import.meta.client) return

  const chat = useLandingChat()
  const stream = useRunStream()

  chat.hydrateFromStorage()

  let timer: ReturnType<typeof setTimeout> | null = null

  watch(
    () => [
      stream.events.value.length,
      stream.activeRunId.value,
      stream.boundConvId.value,
      stream.running.value,
      stream.polling.value,
    ] as const,
    () => {
      const convId = stream.boundConvId.value
      if (!convId || stream.events.value.length === 0) return

      if (timer) clearTimeout(timer)
      timer = setTimeout(() => {
        chat.saveRunEventsForConversation(
          convId,
          stream.events.value,
          stream.activeRunId.value,
        )
      }, 1000)
    },
    { flush: 'post' },
  )
})
