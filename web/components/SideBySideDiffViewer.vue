<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  buildDisplayDiff,
  buildSplitDiffRows,
  splitCellClass,
  type SplitDiffRow,
} from '../utils/diffDisplay'
import {
  assessFileChange,
  formatStatsSummary,
  type FileChangeEvidence,
} from '../utils/approvalReview'

const props = defineProps<{
  diff?: string
  path?: string
  changeType?: string
  after?: string
  before?: string
  /** Full-width command text (no split columns). */
  commandText?: string
  reviewBlocked?: boolean
  reviewBlockReason?: string | null
}>()

const copied = ref(false)
const leftScroll = ref<HTMLElement | null>(null)
const rightScroll = ref<HTMLElement | null>(null)

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

const evidence = computed((): FileChangeEvidence => ({
  path: props.path,
  change_type: props.changeType,
  before: props.before,
  after: props.after,
  diff: props.diff,
}))

const assessment = computed(() =>
  assessFileChange(evidence.value, props.reviewBlocked, props.reviewBlockReason),
)

const statsSummary = computed(() =>
  formatStatsSummary(
    assessment.value.stats,
    String(props.before ?? ''),
    String(props.after ?? ''),
  ),
)

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

function syncScroll(from: 'left' | 'right', event: Event) {
  const source = event.target as HTMLElement
  const target = from === 'left' ? rightScroll.value : leftScroll.value
  if (target) {
    target.scrollTop = source.scrollTop
  }
}

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
    <div class="flex shrink-0 flex-col gap-2 border-b border-zinc-800 px-3 py-2">
      <div class="flex items-center justify-between gap-2">
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
      <p v-if="!isCommand && splitRows.length" class="text-[11px] text-zinc-500">
        {{ statsSummary }}
      </p>
      <div v-if="!isCommand && splitRows.length" class="flex gap-3 text-[10px] text-zinc-500">
        <span><span class="inline-block h-2 w-2 rounded-sm bg-rose-950/80 align-middle" /> Removed</span>
        <span><span class="inline-block h-2 w-2 rounded-sm bg-emerald-950/80 align-middle" /> Added</span>
        <span><span class="inline-block h-2 w-2 rounded-sm bg-zinc-800 align-middle" /> Unchanged</span>
      </div>
    </div>

    <div
      v-if="assessment.blocked"
      class="shrink-0 border-b border-amber-800/60 bg-amber-950/40 px-3 py-2 text-xs text-amber-200"
      data-testid="diff-review-warning"
    >
      <p class="font-semibold">Cannot apply safely</p>
      <p class="mt-1">{{ assessment.reason }}</p>
      <p class="mt-1 text-amber-200/80">
        Approving would replace the entire file. Reject and re-run the executor with complete file contents or a valid diff.
      </p>
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
        <div
          ref="leftScroll"
          class="min-h-0 flex-1 overflow-auto"
          @scroll="syncScroll('left', $event)"
        >
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
        <div
          ref="rightScroll"
          class="min-h-0 flex-1 overflow-auto"
          @scroll="syncScroll('right', $event)"
        >
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
