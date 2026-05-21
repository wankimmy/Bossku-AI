<script setup lang="ts">
import type { Run } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()

const { data, pending } = await useAsyncData('dashboard', () => api.get('/dashboard') as Promise<{
  total_runs?: number
  runs_today?: number
  skills_count?: number
  memory_count?: number
  recent_runs?: Run[]
  agents?: { role: string; status?: string }[]
} | null>)

const kpis = computed(() => [
  { label: 'Total Runs', value: data.value?.total_runs ?? 0 },
  { label: 'Runs Today', value: data.value?.runs_today ?? 0 },
  { label: 'Skills', value: data.value?.skills_count ?? 0 },
  { label: 'Memory', value: data.value?.memory_count ?? 0 },
])

const recentRuns = computed<Run[]>(() => data.value?.recent_runs ?? [])
const agents = computed(() => data.value?.agents ?? [])
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-bold text-zinc-100">Dashboard</h1>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <template v-else>
      <!-- KPI Strip -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <DashboardKpiCard
          v-for="kpi in kpis"
          :key="kpi.label"
          :label="kpi.label"
          :value="kpi.value"
        />
      </div>

      <!-- Recent Runs -->
      <DashboardRecentRunsTable :runs="recentRuns" />

      <!-- Agent Statuses -->
      <div v-if="agents.length" class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
        <h3 class="text-sm font-semibold text-zinc-100 mb-3">Recent Agents</h3>
        <div class="space-y-2">
          <div
            v-for="agent in agents"
            :key="agent.role"
            class="flex items-center justify-between text-sm"
          >
            <span class="text-zinc-300 font-mono">{{ agent.role }}</span>
            <span class="text-xs text-zinc-500">{{ agent.status ?? 'idle' }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
