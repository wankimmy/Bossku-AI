<script setup lang="ts">
import type { LandingConversation } from '~/composables/useLandingChat'

defineProps<{
  conversations: LandingConversation[]
  activeId: string | null
}>()

const emit = defineEmits<{
  select: [id: string]
  newConversation: []
  delete: [id: string]
}>()

function formatWhen(ts: number) {
  const d = new Date(ts)
  const now = new Date()
  const sameDay = d.toDateString() === now.toDateString()
  if (sameDay) {
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' })
}
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
        Conversations
      </h2>
      <button
        type="button"
        class="rounded-md bg-emerald-800/80 px-2 py-1 text-xs font-medium text-emerald-100 hover:bg-emerald-700"
        @click="emit('newConversation')"
      >
        + New
      </button>
    </div>

    <ul class="max-h-[min(50vh,420px)] overflow-y-auto py-1" role="listbox">
      <li v-if="conversations.length === 0" class="px-3 py-4 text-center text-xs text-zinc-500">
        No conversations yet
      </li>
      <li
        v-for="conv in conversations"
        :key="conv.id"
        role="option"
        :aria-selected="activeId === conv.id"
      >
        <button
          type="button"
          class="group flex w-full items-start gap-2 px-3 py-2.5 text-left transition"
          :class="activeId === conv.id
            ? 'bg-emerald-950/40 text-zinc-100'
            : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200'"
          @click="emit('select', conv.id)"
        >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ conv.title }}</span>
            <span class="mt-0.5 block text-[10px] text-zinc-500">
              {{ conv.turns.length }} message{{ conv.turns.length === 1 ? '' : 's' }}
              · {{ formatWhen(conv.updatedAt) }}
            </span>
          </span>
          <button
            type="button"
            class="shrink-0 rounded p-1 text-zinc-600 opacity-0 hover:bg-zinc-800 hover:text-zinc-300 group-hover:opacity-100"
            title="Delete conversation"
            @click.stop="emit('delete', conv.id)"
          >
            ×
          </button>
        </button>
      </li>
    </ul>
  </section>
</template>
