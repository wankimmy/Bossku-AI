<script setup lang="ts">
import type { LearningEvent } from '~/types/api'

const props = defineProps<{ events: LearningEvent[] }>()
const emit = defineEmits<{ accept: [id: string]; reject: [id: string] }>()

const api = useApi()

async function accept(id: string) {
  await api.post(`/learning/${id}/accept`)
  emit('accept', id)
}

async function reject(id: string) {
  await api.post(`/learning/${id}/reject`)
  emit('reject', id)
}
</script>

<template>
  <div class="space-y-3">
    <div v-if="events.length === 0" class="text-sm text-zinc-500 py-4 text-center">No pending learning events.</div>
    <div
      v-for="event in events"
      :key="event.id"
      class="rounded-lg bg-zinc-900 border border-zinc-800 p-4"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-mono text-zinc-500">{{ event.type }}</span>
            <span v-if="event.confidence != null" class="text-xs text-zinc-600">
              confidence: {{ (event.confidence * 100).toFixed(0) }}%
            </span>
          </div>
          <p class="text-sm text-zinc-300 whitespace-pre-wrap">{{ event.content || '(no content)' }}</p>
        </div>
        <div class="flex gap-2 shrink-0">
          <button
            type="button"
            class="px-2 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50"
            @click="accept(event.id)"
          >
            Accept
          </button>
          <button
            type="button"
            class="px-2 py-1 text-xs rounded bg-red-900/50 text-red-300 border border-red-800 hover:bg-red-800/50"
            @click="reject(event.id)"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
