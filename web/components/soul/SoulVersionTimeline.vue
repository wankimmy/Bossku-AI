<script setup lang="ts">
import type { SoulVersion } from '~/types/api'

defineProps<{
  versions: SoulVersion[]
}>()

function formatDate(dateStr?: string) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString()
}
</script>

<template>
  <ol class="relative border-l border-zinc-800 space-y-6 pl-6">
    <li v-for="v in versions" :key="v.id" class="relative">
      <!-- dot -->
      <span
        class="absolute -left-[1.3125rem] top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full border-2"
        :class="v.is_active ? 'border-emerald-500 bg-emerald-500' : 'border-zinc-700 bg-zinc-900'"
      />

      <div class="space-y-0.5">
        <div class="flex flex-wrap items-center gap-2">
          <span class="rounded border border-zinc-700 px-2 py-0.5 font-mono text-xs text-zinc-300">v{{ v.version }}</span>
          <span v-if="v.is_active" class="rounded-full bg-emerald-900/50 px-2 py-0.5 text-xs text-emerald-400">Active</span>
          <span class="text-xs text-zinc-500">{{ formatDate(v.created_at) }}</span>
        </div>
        <p v-if="v.change_summary" class="text-sm text-zinc-400">{{ v.change_summary }}</p>
      </div>
    </li>
    <li v-if="!versions.length" class="text-sm text-zinc-500">No versions yet.</li>
  </ol>
</template>
