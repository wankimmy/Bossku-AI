<script setup lang="ts">
import type { LlmProvider } from '~/types/api'

defineProps<{ provider: LlmProvider }>()

const healthCls = (h: string) => {
  switch (h) {
    case 'healthy': return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
    case 'degraded': return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
    case 'offline': return 'bg-red-900/50 text-red-300 border-red-800'
    default: return 'bg-zinc-800 text-zinc-400 border-zinc-700'
  }
}
</script>

<template>
  <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
    <div class="flex items-start justify-between gap-2">
      <div>
        <h3 class="text-sm font-semibold text-zinc-100">{{ provider.name }}</h3>
        <p class="text-xs text-zinc-500 mt-0.5">{{ provider.type }}</p>
      </div>
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs border" :class="healthCls(provider.health_status)">
        {{ provider.health_status }}
      </span>
    </div>
    <div class="mt-3 space-y-1">
      <div v-if="provider.base_url" class="text-xs text-zinc-500 font-mono truncate">{{ provider.base_url }}</div>
      <div v-if="provider.api_key_masked" class="flex items-center gap-1 text-xs text-zinc-500">
        <span>🔑</span>
        <span class="font-mono">{{ provider.api_key_masked }}</span>
      </div>
      <div class="flex items-center gap-1.5 mt-2">
        <span
          class="inline-block w-2 h-2 rounded-full"
          :class="provider.is_active ? 'bg-emerald-400' : 'bg-zinc-600'"
        />
        <span class="text-xs text-zinc-500">{{ provider.is_active ? 'Active' : 'Inactive' }}</span>
      </div>
    </div>
  </div>
</template>
