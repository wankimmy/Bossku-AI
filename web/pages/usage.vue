<script setup lang="ts">
import type { UsageEvent, UsageSummary } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()

const { data: summaryData, pending: summaryPending } = await useAsyncData<UsageSummary>(
  'usage-summary',
  () => api.get('/usage/summary'),
)

const { data: eventsData, pending: eventsPending } = await useAsyncData<UsageEvent[] | { data?: UsageEvent[] }>(
  'usage-events',
  () => api.get('/usage'),
)

const events = computed<UsageEvent[]>(() => {
  const d = eventsData.value
  if (!d) return []
  return Array.isArray(d) ? d : (d.data ?? [])
})

const summary = computed<UsageSummary | null>(() => summaryData.value ?? null)
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-bold text-zinc-100">Usage</h1>

    <!-- Summary Cards -->
    <div v-if="!summaryPending && summary" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
      <DashboardKpiCard
        label="Total Tokens"
        :value="summary.total_tokens?.toLocaleString() ?? 0"
      />
      <DashboardKpiCard
        label="Total Cost"
        :value="`$${(summary.total_cost ?? 0).toFixed(4)}`"
      />
      <div v-if="summary.by_provider" class="rounded-lg bg-zinc-900 border border-zinc-800 p-4 col-span-2 sm:col-span-1">
        <div class="text-xs text-zinc-500 uppercase tracking-wide mb-2">By Provider</div>
        <div
          v-for="(val, provider) in summary.by_provider"
          :key="provider"
          class="flex justify-between text-xs py-1"
        >
          <span class="text-zinc-400">{{ provider }}</span>
          <span class="text-zinc-300">${{ val.cost?.toFixed(4) }} · {{ val.tokens?.toLocaleString() }} tokens</span>
        </div>
      </div>
    </div>

    <!-- Events Table -->
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <div class="px-4 py-3 border-b border-zinc-800">
        <h3 class="text-sm font-semibold text-zinc-100">Usage Events</h3>
      </div>
      <div v-if="eventsPending" class="px-4 py-6 text-sm text-zinc-500">Loading...</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-zinc-800">
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Model</th>
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Prompt Tokens</th>
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Completion Tokens</th>
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Total</th>
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Cost</th>
              <th class="px-4 py-2 text-left text-xs text-zinc-500">Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="events.length === 0">
              <td colspan="6" class="px-4 py-6 text-center text-zinc-500 text-xs">No usage events.</td>
            </tr>
            <tr
              v-for="ev in events"
              :key="ev.id"
              class="border-b border-zinc-800/50 hover:bg-zinc-800/30"
            >
              <td class="px-4 py-2 text-xs font-mono text-zinc-300">{{ ev.model ?? '—' }}</td>
              <td class="px-4 py-2 text-xs text-zinc-400">{{ ev.prompt_tokens?.toLocaleString() ?? '—' }}</td>
              <td class="px-4 py-2 text-xs text-zinc-400">{{ ev.completion_tokens?.toLocaleString() ?? '—' }}</td>
              <td class="px-4 py-2 text-xs text-zinc-300">{{ ev.total_tokens?.toLocaleString() ?? '—' }}</td>
              <td class="px-4 py-2 text-xs text-emerald-400">{{ ev.cost != null ? `$${ev.cost.toFixed(6)}` : '—' }}</td>
              <td class="px-4 py-2 text-xs text-zinc-500">{{ ev.created_at ? new Date(ev.created_at).toLocaleString() : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
