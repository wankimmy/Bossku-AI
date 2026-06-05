<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  buildDisplayDiff,
  diffLineClass,
  diffLinePrefix,
  parseDiffLines,
} from '../utils/diffDisplay'

const props = defineProps<{
  diff?: string
  path?: string
  changeType?: string
  after?: string
  before?: string
}>()

const wrap = ref(true)
const copied = ref(false)

const displayDiff = computed(() =>
  buildDisplayDiff({
    path: props.path ?? 'file',
    change_type: props.changeType ?? 'modified',
    diff: props.diff,
    after: props.after,
    before: props.before,
  }),
)

const displayLines = computed(() => {
  const text = displayDiff.value
  if (!text) return []
  return parseDiffLines(text)
})

const isNewFile = computed(() => props.changeType === 'created')

async function copyDiff() {
  const text = displayDiff.value
  if (!text || !navigator?.clipboard) return
  await navigator.clipboard.writeText(text)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1200)
}
</script>

<template>
  <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-2 py-1.5 text-xs dark:border-zinc-800">
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1">
          <input v-model="wrap" type="checkbox" class="h-3 w-3">
          Wrap lines
        </label>
        <span
          v-if="isNewFile"
          class="rounded border border-emerald-700/50 bg-emerald-950/30 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-400"
        >
          New file
        </span>
      </div>
      <button
        type="button"
        class="rounded border border-zinc-300 px-2 py-0.5 disabled:opacity-50 dark:border-zinc-700"
        :disabled="!displayDiff"
        @click="copyDiff"
      >
        {{ copied ? 'Copied' : 'Copy diff' }}
      </button>
    </div>

    <div
      v-if="displayLines.length"
      class="max-h-72 overflow-auto"
      :class="wrap ? '' : 'overflow-x-auto'"
    >
      <div
        v-for="(line, i) in displayLines"
        :key="i"
        class="font-mono text-xs px-2 py-0.5 flex gap-2 min-w-0"
        :class="[diffLineClass(line.kind), wrap ? 'whitespace-pre-wrap break-words' : 'whitespace-pre']"
      >
        <span class="select-none shrink-0 w-3 text-center opacity-60">{{ diffLinePrefix(line.kind) }}</span>
        <span class="min-w-0 flex-1">{{ line.text }}</span>
      </div>
    </div>

    <p v-else class="p-3 text-xs text-zinc-500">
      No diff captured for this change.
    </p>
  </div>
</template>
