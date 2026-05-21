<script setup lang="ts">
import type { LogEntry } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const levelFilter = ref('')
const searchFilter = ref('')
const runIdFilter = ref('')
const page = ref(1)
const perPage = 50

const { data, pending, refresh } = await useAsyncData<{ data: LogEntry[]; total?: number } | LogEntry[]>(
  'logs-page',
  () => api.get('/logs', {
    level: levelFilter.value || undefined,
    search: searchFilter.value || undefined,
    run_id: runIdFilter.value || undefined,
    page: page.value,
    per_page: perPage,
  }),
  { watch: [levelFilter, searchFilter, runIdFilter, page] },
)

const logs = computed<LogEntry[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: LogEntry[] }).data ?? []
})

const total = computed(() => {
  const d = data.value
  if (!d || Array.isArray(d)) return logs.value.length
  return (d as { total?: number }).total ?? logs.value.length
})

const levelCls = (level: string) => {
  switch (level) {
    case 'error': return 'bg-red-900/50 text-red-300 border-red-800'
    case 'warning': return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
    case 'info': return 'bg-blue-900/50 text-blue-300 border-blue-800'
    case 'debug': return 'bg-zinc-800 text-zinc-400 border-zinc-700'
    default: return 'bg-zinc-800 text-zinc-400 border-zinc-700'
  }
}

function truncate(s?: string, n = 100) {
  if (!s) return '—'
  return s.length > n ? s.slice(0, n) + '...' : s
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold text-zinc-100">Logs</h1>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <select
        v-model="levelFilter"
        class="bg-zinc-800 border border-zinc-700 text-sm text-zinc-100 rounded px-2 py-1.5 outline-none"
      >
        <option value="">All levels</option>
        <option value="debug">Debug</option>
        <option value="info">Info</option>
        <option value="warning">Warning</option>
        <option value="error">Error</option>
      </select>
      <input
        v-model="searchFilter"
        type="text"
        placeholder="Search messages..."
        class="bg-zinc-800 border border-zinc-700 text-sm text-zinc-100 rounded px-3 py-1.5 outline-none placeholder-zinc-600"
      >
      <input
        v-model="runIdFilter"
        type="text"
        placeholder="Filter by run ID..."
        class="bg-zinc-800 border border-zinc-700 text-sm text-zinc-100 rounded px-3 py-1.5 outline-none placeholder-zinc-600"
      >
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-xs font-mono">
          <thead>
            <tr class="border-b border-zinc-800">
              <th class="px-4 py-2 text-left text-zinc-500 font-medium">Timestamp</th>
              <th class="px-4 py-2 text-left text-zinc-500 font-medium">Level</th>
              <th class="px-4 py-2 text-left text-zinc-500 font-medium">Channel</th>
              <th class="px-4 py-2 text-left text-zinc-500 font-medium w-64">Message</th>
              <th class="px-4 py-2 text-left text-zinc-500 font-medium">Source</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="logs.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No log entries.</td>
            </tr>
            <tr
              v-for="(log, i) in logs"
              :key="log.id || i"
              class="border-b border-zinc-800/50 hover:bg-zinc-800/30"
            >
              <td class="px-4 py-2 text-zinc-500 whitespace-nowrap">{{ log.timestamp ?? '—' }}</td>
              <td class="px-4 py-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs border" :class="levelCls(log.level)">
                  {{ log.level }}
                </span>
              </td>
              <td class="px-4 py-2 text-zinc-400">{{ log.channel ?? '—' }}</td>
              <td class="px-4 py-2 text-zinc-300 max-w-xs">{{ truncate(log.message) }}</td>
              <td class="px-4 py-2 text-zinc-500">{{ log.source ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between text-xs text-zinc-500">
      <span>{{ total }} total entries</span>
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-1 rounded border border-zinc-700 hover:bg-zinc-800 disabled:opacity-40"
          :disabled="page <= 1"
          @click="page--"
        >
          ← Prev
        </button>
        <span class="px-2 py-1">Page {{ page }}</span>
        <button
          type="button"
          class="px-3 py-1 rounded border border-zinc-700 hover:bg-zinc-800 disabled:opacity-40"
          :disabled="logs.length < perPage"
          @click="page++"
        >
          Next →
        </button>
      </div>
    </div>
  </div>
</template>
