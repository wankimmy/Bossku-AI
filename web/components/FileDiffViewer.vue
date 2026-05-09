<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ diff?: string }>()
const wrap = ref(true)
const copied = ref(false)

async function copyDiff() {
  if (!props.diff || !navigator?.clipboard) return
  await navigator.clipboard.writeText(props.diff)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1200)
}
</script>

<template>
  <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-2 py-1.5 text-xs dark:border-zinc-800">
      <label class="flex items-center gap-1">
        <input v-model="wrap" type="checkbox" class="h-3 w-3">
        Wrap lines
      </label>
      <button
        type="button"
        class="rounded border border-zinc-300 px-2 py-0.5 disabled:opacity-50 dark:border-zinc-700"
        :disabled="!diff"
        @click="copyDiff"
      >
        {{ copied ? 'Copied' : 'Copy diff' }}
      </button>
    </div>
    <pre
      class="max-h-72 overflow-auto p-3 text-xs leading-relaxed"
      :class="wrap ? 'whitespace-pre-wrap break-words' : 'whitespace-pre'"
    >{{ diff || 'No diff captured for this change.' }}</pre>
  </div>
</template>
