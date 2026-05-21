<script setup lang="ts">
import type { BrainData } from '~/types/api'

defineProps<{ brainData: BrainData | null }>()
</script>

<template>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
      <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Learning Events</div>
      <div class="text-2xl font-bold text-emerald-400">{{ brainData?.learning_events?.total ?? 0 }}</div>
      <div class="text-xs text-zinc-500 mt-1">
        {{ brainData?.learning_events?.pending ?? 0 }} pending
      </div>
    </div>
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
      <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Skill Candidates</div>
      <div class="text-2xl font-bold text-blue-400">{{ brainData?.skill_candidates?.total ?? 0 }}</div>
      <div class="text-xs text-zinc-500 mt-1">
        {{ brainData?.skill_candidates?.pending ?? 0 }} pending review
      </div>
    </div>
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
      <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Unprocessed Feedback</div>
      <div class="text-2xl font-bold text-yellow-400">{{ brainData?.unprocessed_feedback ?? 0 }}</div>
    </div>
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
      <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Conflicts</div>
      <div class="text-2xl font-bold text-red-400">{{ brainData?.conflicts ?? 0 }}</div>
      <div class="text-xs text-zinc-500 mt-1">{{ brainData?.knowledge_nodes ?? 0 }} nodes</div>
    </div>
  </div>
</template>
