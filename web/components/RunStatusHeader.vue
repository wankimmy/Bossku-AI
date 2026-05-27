<script setup lang="ts">
import { computed } from 'vue'
import type { RoutingSummary } from '../types/bossku'

const props = defineProps<{
  status?: string
  running?: boolean
  memoryUsed?: boolean
  routing?: RoutingSummary
}>()

const statusLabel = computed(() => (props.running ? 'running' : (props.status || 'idle')))
const memoryLabel = computed(() => (props.memoryUsed ? 'used' : 'not used'))
</script>

<template>
  <header class="space-y-3" data-testid="run-status-header">
    <div>
      <h1 class="text-lg font-semibold text-zinc-950 dark:text-zinc-50">
        BosskuAI agent workspace
      </h1>
      <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        Run a task through BosskuAI's visible agent workflow: plan, execute, audit, and finalize with Ollama models.
      </p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <DashboardStatCard
        label="Status"
        :value="statusLabel"
        variant="status"
        :active="running"
        :pulse="running"
      />
      <DashboardStatCard
        label="Model backend"
        value="Ollama"
        variant="backend"
      />
      <DashboardStatCard
        label="Memory"
        :value="memoryLabel"
        variant="memory"
        :active="memoryUsed"
      />
    </div>

    <div
      v-if="routing"
      class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
      data-testid="run-status-model-cards"
    >
      <DashboardStatCard
        label="Reasoning model"
        :value="routing.reasoningModel || '—'"
        variant="reasoning"
      />
      <DashboardStatCard
        label="Coding model"
        :value="routing.codingModel || '—'"
        variant="coding"
      />
      <DashboardStatCard
        label="Review model"
        :value="routing.reviewModel || '—'"
        variant="review"
      />
      <DashboardStatCard
        label="Fast model"
        :value="routing.fastModel || '—'"
        variant="fast"
      />
    </div>
  </header>
</template>
