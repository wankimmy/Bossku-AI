<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  buildDisplayDiff,
  buildSplitDiffRows,
  splitCellClass,
  type SplitDiffRow,
} from '../utils/diffDisplay'

const props = defineProps<{
  diff?: string
  path?: string
  changeType?: string
  after?: string
  before?: string
  /** Full-width command text (no split columns). */
  commandText?: string
}>()

const copied = ref(false)

const displayPath = computed(() => props.path?.trim() || 'file')

const changeLabel = computed(() => {
  const t = props.changeType ?? 'modified'
  if (t === 'created') return 'New file'
  if (t === 'deleted') return 'Deleted'
  return 'Modified'
})

const changeBadgeClass = computed(() => {
  const t = props.changeType ?? 'modified'
  if (t === 'created') return 'border-emerald-700/50 bg-emerald-950/30 text-emerald-400'
  if (t === 'deleted') return 'border-rose-700/50 bg-rose-950/30 text-rose-400'
  return 'border-zinc-600 bg-zinc-800/80 text-zinc-400'
})

const isCommand = computed(() => Boolean(props.commandText?.trim()))

const splitRows = computed((): SplitDiffRow[] => {
  if (isCommand.value) {
    return []
  }

  return buildSplitDiffRows({
    path: props.path,
    change_type: props.changeType,
    diff: props.diff,
    after: props.after,
    before: props.before,
  })
})

const unifiedForCopy = computed(() =>
  props.commandText?.trim()
  || buildDisplayDiff({
    path: props.path,
    change_type: props.changeType,
    diff: props.diff,
    after: props.after,
    before: props.before,
  })
  || '',
)

async function copyDiff() {
  const text = unifiedForCopy.value
  if (!text || !navigator?.clipboard) return
  await navigator.clipboard.writeText(text)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1200)
}
</script>

<template>
  <div
    class="flex min-h-0 flex-1 flex-col rounded-lg border border-zinc-700/80 bg-zinc-950"
    data-testid="side-by-side-diff"
  >
    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-zinc-800 px-3 py-2">
      <div class="flex min-w-0 items-center gap-2">
        <span class="truncate font-mono text-xs text-zinc-300">{{ displayPath }}</span>
        <span
          class="shrink-0 rounded border px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide"
          :class="changeBadgeClass"
        >
          {{ changeLabel }}
        </span>
      </div>
      <button
        v-if="!isCommand"
        type="button"
        class="shrink-0 rounded border border-zinc-600 px-2 py-0.5 text-[11px] text-zinc-400 hover:border-zinc-500 hover:text-zinc-200 disabled:opacity-40"
        :disabled="splitRows.length === 0"
        @click="copyDiff"
      >
        {{ copied ? 'Copied' : 'Copy diff' }}
      </button>
    </div>

    <pre
      v-if="isCommand"
      class="min-h-0 flex-1 overflow-auto p-3 font-mono text-xs text-zinc-300 whitespace-pre-wrap"
    >{{ commandText }}</pre>

    <div
      v-else-if="splitRows.length"
      class="grid min-h-0 flex-1 grid-cols-2 divide-x divide-zinc-800"
    >
      <div class="flex min-h-0 min-w-0 flex-col">
        <div class="shrink-0 border-b border-zinc-800 bg-zinc-900/80 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
          Previous
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
          <div
            v-for="(row, i) in splitRows"
            :key="`l-${i}`"
            class="flex min-w-0 font-mono text-xs leading-5"
          >
            <span class="w-10 shrink-0 select-none border-r border-zinc-800/80 bg-zinc-900/60 px-1 text-right text-[10px] text-zinc-600">
              {{ row.leftNum ?? '' }}
            </span>
            <span
              class="min-w-0 flex-1 px-2 py-0.5 whitespace-pre"
              :class="splitCellClass(row.leftKind, 'left')"
            >{{ row.leftLine || ' ' }}</span>
          </div>
        </div>
      </div>

      <div class="flex min-h-0 min-w-0 flex-col">
        <div class="shrink-0 border-b border-zinc-800 bg-zinc-900/80 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
          Updated
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
          <div
            v-for="(row, i) in splitRows"
            :key="`r-${i}`"
            class="flex min-w-0 font-mono text-xs leading-5"
          >
            <span class="w-10 shrink-0 select-none border-r border-zinc-800/80 bg-zinc-900/60 px-1 text-right text-[10px] text-zinc-600">
              {{ row.rightNum ?? '' }}
            </span>
            <span
              class="min-w-0 flex-1 px-2 py-0.5 whitespace-pre"
              :class="splitCellClass(row.rightKind, 'right')"
            >{{ row.rightLine || ' ' }}</span>
          </div>
        </div>
      </div>
    </div>

    <p v-else class="p-4 text-xs text-zinc-500">
      No diff captured for this change.
    </p>
  </div>
</template>
