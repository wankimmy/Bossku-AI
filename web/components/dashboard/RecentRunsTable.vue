<script setup lang="ts">
import type { Run } from '~/types/api'

defineProps<{ runs: Run[] }>()

function truncate(s: string, n = 50) {
  return s && s.length > n ? s.slice(0, n) + '...' : s
}

function shortId(id: string) {
  return id.slice(0, 8)
}
</script>

<template>
  <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
    <div class="px-4 py-3 border-b border-zinc-800">
      <h3 class="text-sm font-semibold text-zinc-100">Recent Runs</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800 text-left">
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">ID</th>
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">Prompt</th>
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">Status</th>
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">Risk</th>
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">Cost</th>
            <th class="px-4 py-2 text-xs text-zinc-500 font-medium">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="runs.length === 0">
            <td colspan="6" class="px-4 py-6 text-center text-zinc-500 text-xs">No runs yet.</td>
          </tr>
          <tr
            v-for="run in runs"
            :key="run.id"
            class="border-b border-zinc-800/50 hover:bg-zinc-800/30 transition-colors"
          >
            <td class="px-4 py-2.5">
              <NuxtLink :to="`/runs/${run.id}`" class="font-mono text-xs text-emerald-400 hover:underline">
                {{ shortId(run.id) }}
              </NuxtLink>
            </td>
            <td class="px-4 py-2.5 text-zinc-300 max-w-xs">
              <span class="text-xs">{{ truncate(run.prompt) }}</span>
            </td>
            <td class="px-4 py-2.5">
              <RunsRunStatusBadge :status="run.status" />
            </td>
            <td class="px-4 py-2.5">
              <RunsRiskBadge :risk-level="run.risk_level" />
            </td>
            <td class="px-4 py-2.5 text-xs text-zinc-400">
              {{ run.estimated_cost != null ? `$${run.estimated_cost.toFixed(4)}` : '—' }}
            </td>
            <td class="px-4 py-2.5 text-xs text-zinc-500">
              {{ run.created_at ? new Date(run.created_at).toLocaleString() : '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
