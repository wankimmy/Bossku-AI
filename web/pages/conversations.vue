<script setup lang="ts">
import type { LandingConversation } from '~/composables/useLandingChat'
import { loadActiveRunBinding } from '~/utils/activeRunStorage'
import { isTerminalStreamEvent } from '~/utils/runStreamTerminal'

definePageMeta({ layout: 'default' })

const chat = useLandingChat()
const router = useRouter()
const previewId = ref<string | null>(null)
const { running, polling, boundConvId } = useRunStream()

onMounted(() => {
  chat.hydrateFromStorage()
  syncPreviewFromList()
})

function syncPreviewFromList() {
  const list = chat.conversations.value
  if (list.length === 0) {
    previewId.value = null
    return
  }
  if (!previewId.value || !list.some(c => c.id === previewId.value)) {
    previewId.value = list[0]?.id ?? null
  }
}

watch(() => chat.conversations.value, syncPreviewFromList)

const previewConversation = computed((): LandingConversation | null => {
  const id = previewId.value
  if (!id) return null
  return chat.conversations.value.find(c => c.id === id) ?? null
})

const previewTurns = computed(() => {
  const turns = previewConversation.value?.turns ?? []
  return [...turns].sort((a, b) => a.createdAt - b.createdAt)
})

const inProgressConvIds = computed(() => {
  const ids = new Set<string>()
  const binding = loadActiveRunBinding()
  if ((running.value || polling.value) && boundConvId.value) {
    ids.add(boundConvId.value)
  }
  if (binding?.convId && (running.value || polling.value)) {
    ids.add(binding.convId)
  }
  for (const conv of chat.conversations.value) {
    const events = conv.runEvents ?? []
    const last = events.at(-1)
    if (last && !isTerminalStreamEvent(last)) {
      ids.add(conv.id)
    }
  }
  return [...ids]
})

function onSelect(id: string) {
  previewId.value = id
}

function openInChat(id: string) {
  router.push({ path: '/', query: { conv: id } })
}

function newConversation() {
  const id = chat.startNewConversation()
  openInChat(id)
}

function removeConversation(id: string) {
  chat.deleteConversation(id)
  syncPreviewFromList()
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h1 class="text-lg font-semibold text-zinc-100">
          Conversations
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
          Saved on this device. Open a thread in chat to continue with full agent context.
        </p>
      </div>
      <NuxtLink
        to="/"
        class="text-sm text-zinc-400 hover:text-emerald-400"
      >
        ← Back to chat
      </NuxtLink>
    </div>

    <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
      <ConversationSidebar
        :conversations="chat.conversations.value"
        :active-id="previewId"
        :in-progress-ids="inProgressConvIds"
        @select="onSelect"
        @new-conversation="newConversation"
        @delete="removeConversation"
      />

      <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <template v-if="previewConversation">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
            <div class="min-w-0">
              <h2 class="truncate text-sm font-semibold text-zinc-100">
                {{ previewConversation.title }}
              </h2>
              <p class="text-xs text-zinc-500">
                {{ previewConversation.turns.length }} message{{ previewConversation.turns.length === 1 ? '' : 's' }}
              </p>
            </div>
            <button
              type="button"
              class="shrink-0 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
              @click="openInChat(previewConversation.id)"
            >
              Continue in chat
            </button>
          </div>

          <div v-if="previewTurns.length > 0" class="mt-4 max-h-[min(70vh,640px)] space-y-3 overflow-y-auto">
            <ChatTurnBubble
              v-for="turn in previewTurns"
              :key="turn.id"
              :turn="turn"
            />
          </div>
          <p v-else class="mt-6 text-center text-sm text-zinc-500">
            No messages in this thread yet.
          </p>
        </template>

        <p v-else class="py-12 text-center text-sm text-zinc-500">
          No saved conversations yet. Start one from the
          <NuxtLink to="/" class="text-emerald-500 hover:underline">chat page</NuxtLink>.
        </p>
      </section>
    </div>
  </div>
</template>
