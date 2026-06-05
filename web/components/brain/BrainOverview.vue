<script setup lang="ts">
import type { BrainData } from '~/types/api'

defineProps<{
  brainData: BrainData | null
}>()
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-violet-900/40 bg-violet-950/20 p-4">
      <div class="text-xs font-medium uppercase tracking-wide text-violet-400/80">
        Memories
      </div>
      <div class="mt-1 text-2xl font-bold text-fuchsia-300">
        {{ brainData?.memory_count ?? 0 }}
      </div>
      <div v-if="brainData?.memory_confidence?.avg != null" class="mt-1 text-xs text-zinc-500">
        avg confidence {{ Math.round((brainData.memory_confidence.avg ?? 0) * 100) }}%
      </div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
        Learning events
      </div>
      <div class="mt-1 text-2xl font-bold text-emerald-400">
        {{ brainData?.learning_events?.total ?? 0 }}
      </div>
      <div class="mt-1 text-xs text-zinc-500">
        {{ brainData?.learning_events?.pending ?? 0 }} pending
      </div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
        Skill candidates
      </div>
      <div class="mt-1 text-2xl font-bold text-blue-400">
        {{ brainData?.skill_candidates?.total ?? 0 }}
      </div>
      <div class="mt-1 text-xs text-zinc-500">
        {{ brainData?.skill_candidates?.pending ?? 0 }} pending review
      </div>
    </div>
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
        Knowledge graph
      </div>
      <div class="mt-1 text-2xl font-bold text-indigo-400">
        {{ brainData?.knowledge_nodes ?? 0 }}
      </div>
      <div class="mt-1 text-xs text-zinc-500">
        {{ brainData?.conflicts ?? 0 }} conflicts
      </div>
    </div>
  </div>
</template>
