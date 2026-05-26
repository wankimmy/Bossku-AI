<script setup lang="ts">
import type { FeedbackItem } from '~/types/api'
import { paginatedItems, paginatedTotal } from '~/utils/paginatedResponse'

definePageMeta({ layout: 'default' })

const api = useApi()

const { data, pending, refresh } = await useAsyncData('feedback', () => api.get('/feedback'))

const items = computed(() => paginatedItems<FeedbackItem>(data.value))

const stats = computed(() => {
  const total = paginatedTotal(data.value)
  const unprocessed = items.value.filter(i => !i.processed).length
  return { total, unprocessed }
})

const signalConfig: Record<string, { label: string; class: string }> = {
  positive: { label: 'Positive', class: 'bg-emerald-900/50 text-emerald-400' },
  negative: { label: 'Negative', class: 'bg-red-900/50 text-red-400' },
  flag: { label: 'Flagged', class: 'bg-yellow-900/50 text-yellow-400' },
  neutral: { label: 'Neutral', class: 'bg-zinc-800 text-zinc-400' },
}

function formatDate(d?: string) {
  return d ? new Date(d).toLocaleString() : '—'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold">Feedback</h1>
        <p class="text-sm text-zinc-400">User and system feedback on runs, skills, and outputs.</p>
      </div>
      <div class="flex gap-3 text-sm text-zinc-400">
        <span>Total: <span class="text-zinc-200">{{ stats.total }}</span></span>
        <span v-if="stats.unprocessed">Unprocessed: <span class="text-yellow-400">{{ stats.unprocessed }}</span></span>
      </div>
    </div>

    <UiSkeleton v-if="pending" class="h-64 w-full" />

    <UiEmptyState v-else-if="items.length === 0" title="No feedback yet." hint="Feedback from runs and UI interactions will appear here." />

    <ul v-else class="divide-y divide-zinc-800 rounded-xl border border-zinc-800">
      <li v-for="item in items" :key="item.id" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
        <div class="space-y-0.5">
          <div class="flex flex-wrap items-center gap-2">
            <span
              :class="(signalConfig[item.signal] ?? signalConfig.neutral).class"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium"
            >
              {{ (signalConfig[item.signal] ?? signalConfig.neutral).label }}
            </span>
            <span class="text-sm text-zinc-300 capitalize">{{ item.target_type }}</span>
            <span v-if="item.target_id" class="font-mono text-xs text-zinc-500">{{ item.target_id }}</span>
          </div>
          <p v-if="item.comment" class="text-sm text-zinc-400">{{ item.comment }}</p>
          <p class="text-xs text-zinc-600">{{ formatDate(item.created_at) }}</p>
        </div>
        <span
          :class="item.processed ? 'text-zinc-500' : 'text-yellow-400'"
          class="text-xs"
        >{{ item.processed ? 'Processed' : 'Pending' }}</span>
      </li>
    </ul>

    <button type="button" class="text-sm underline text-zinc-400 hover:text-zinc-200" @click="refresh()">
      Refresh
    </button>
  </div>
</template>
