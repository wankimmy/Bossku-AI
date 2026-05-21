<script setup lang="ts">
import type { Agent } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending } = await useAsyncData<Agent[]>('agents', () => api.get('/agents'))

const agents = computed<Agent[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: Agent[] }).data ?? []
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-zinc-100">Agents</h1>
      <NuxtLink
        to="/personas"
        class="text-sm text-emerald-400 hover:text-emerald-300 underline"
      >
        Edit personas →
      </NuxtLink>
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="agent in agents"
        :key="agent.role"
        class="rounded-lg bg-zinc-900 border border-zinc-800 p-4"
      >
        <h3 class="text-sm font-semibold text-zinc-100 font-mono">{{ agent.role }}</h3>
        <div class="mt-3 space-y-1.5">
          <div class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Run count</span>
            <span class="text-zinc-300">{{ agent.run_count ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Avg latency</span>
            <span
              class="inline-flex items-center px-1.5 py-0.5 rounded text-xs border border-zinc-700 bg-zinc-800 text-zinc-300"
            >
              {{ agent.avg_latency_ms != null ? agent.avg_latency_ms + 'ms' : '—' }}
            </span>
          </div>
          <div v-if="agent.success_rate != null" class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Success rate</span>
            <span class="text-emerald-400">{{ (agent.success_rate * 100).toFixed(1) }}%</span>
          </div>
        </div>
      </div>
      <div v-if="agents.length === 0" class="col-span-full text-sm text-zinc-500 text-center py-8">
        No agent data yet.
      </div>
    </div>
  </div>
</template>
