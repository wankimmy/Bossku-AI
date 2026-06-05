<script setup lang="ts">
const props = defineProps<{
  suggestion: string
}>()

const emit = defineEmits<{
  accept: []
  dismiss: []
}>()
</script>

<template>
  <div class="rounded-xl border border-yellow-700/50 bg-yellow-950/30 px-4 py-3 space-y-3">
    <div class="flex items-start gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 flex-shrink-0 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-xs font-medium text-yellow-300">
        BosskuAI suggests these soul changes — review before applying.
      </p>
    </div>

    <p class="rounded-lg bg-zinc-900/60 px-3 py-2 text-sm text-zinc-200">{{ suggestion }}</p>

    <div class="flex gap-2">
      <button
        type="button"
        class="rounded-lg bg-yellow-700 px-3 py-1.5 text-xs font-medium text-yellow-100 hover:bg-yellow-600 transition"
        @click="emit('accept')"
      >
        Accept
      </button>
      <button
        type="button"
        class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-400 hover:bg-zinc-800 transition"
        @click="emit('dismiss')"
      >
        Dismiss
      </button>
    </div>
  </div>
</template>
