<script setup lang="ts">
definePageMeta({ layout: 'default' })

const autoGenerateCandidates = ref(true)
const extractLearning = ref(true)
const confidenceThreshold = ref(0.7)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-zinc-100">Learning Settings</h1>
      <p class="text-sm text-zinc-500 mt-1">Configure the automated learning pipeline.</p>
    </div>

    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-6 space-y-5">
      <label class="flex items-center justify-between">
        <div>
          <div class="text-sm font-medium text-zinc-100">Auto-generate skill candidates</div>
          <div class="text-xs text-zinc-500 mt-0.5">Automatically create skill candidates from successful run patterns.</div>
        </div>
        <button
          type="button"
          class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
          :class="autoGenerateCandidates ? 'bg-emerald-600' : 'bg-zinc-700'"
          @click="autoGenerateCandidates = !autoGenerateCandidates"
        >
          <span
            class="inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform"
            :class="autoGenerateCandidates ? 'translate-x-4.5' : 'translate-x-0.5'"
          />
        </button>
      </label>

      <label class="flex items-center justify-between">
        <div>
          <div class="text-sm font-medium text-zinc-100">Extract learning after each run</div>
          <div class="text-xs text-zinc-500 mt-0.5">Run the learning extraction pipeline at the end of every task.</div>
        </div>
        <button
          type="button"
          class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
          :class="extractLearning ? 'bg-emerald-600' : 'bg-zinc-700'"
          @click="extractLearning = !extractLearning"
        >
          <span
            class="inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform"
            :class="extractLearning ? 'translate-x-4.5' : 'translate-x-0.5'"
          />
        </button>
      </label>

      <label class="block">
        <div class="text-sm font-medium text-zinc-100 mb-1">Confidence threshold</div>
        <div class="text-xs text-zinc-500 mb-2">Minimum confidence score to create a skill candidate (0–1).</div>
        <input
          v-model.number="confidenceThreshold"
          type="range"
          min="0"
          max="1"
          step="0.05"
          class="w-full accent-emerald-500"
        >
        <div class="text-xs text-zinc-400 mt-1">{{ (confidenceThreshold * 100).toFixed(0) }}%</div>
      </label>

      <div class="pt-2 border-t border-zinc-800">
        <p class="text-xs text-zinc-600">Settings saved automatically (stub — backend integration pending).</p>
      </div>
    </div>
  </div>
</template>
