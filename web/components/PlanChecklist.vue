<script setup lang="ts">
import type { PlanChecklistItem } from '../types/bossku'

withDefaults(
  defineProps<{ items: PlanChecklistItem[]; embedded?: boolean }>(),
  { embedded: false },
)

function symbol(status: string) {
  if (status === 'awaiting_input') return '?'
  if (status === 'partial') return '...'
  if (['completed', 'success', 'passed'].includes(status)) return '✓'
  if (status === 'running') return '…'
  if (status === 'needs_revision') return '!'
  if (status === 'disputed') return '?'
  if (status === 'unverifiable') return '!'
  if (['failed', 'fail'].includes(status)) return '×'
  if (status === 'skipped') return '-'
  return '○'
}

function rowClasses(status: string) {
  if (['completed', 'success', 'passed'].includes(status))
    return 'border-emerald-700 bg-emerald-950/40'
  if (status === 'awaiting_input')
    return 'border-sky-700 bg-sky-950/40'
  if (status === 'partial')
    return 'border-amber-700 bg-amber-950/40'
  if (status === 'running')
    return 'border-yellow-600 bg-yellow-950/40'
  if (status === 'disputed')
    return 'border-rose-700 bg-rose-950/40'
  if (status === 'unverifiable')
    return 'border-amber-700 bg-amber-950/40'
  if (['failed', 'fail'].includes(status))
    return 'border-rose-700 bg-rose-950/40'
  return 'border-zinc-800'
}

function checkboxClasses(status: string) {
  if (['completed', 'success', 'passed'].includes(status))
    return 'border-emerald-500 bg-emerald-500 text-white'
  if (status === 'awaiting_input')
    return 'border-sky-500 bg-sky-500/20 text-sky-300'
  if (status === 'partial')
    return 'border-amber-500 bg-amber-500/20 text-amber-300'
  if (status === 'running')
    return 'border-yellow-400 bg-yellow-400/20 text-yellow-300'
  if (status === 'disputed')
    return 'border-rose-500 bg-rose-500/20 text-rose-400'
  if (status === 'unverifiable')
    return 'border-amber-500 bg-amber-500/20 text-amber-300'
  if (['failed', 'fail'].includes(status))
    return 'border-rose-500 bg-rose-500/20 text-rose-400'
  return 'border-zinc-600 text-zinc-400'
}

function statusBadgeClasses(status: string) {
  if (['completed', 'success', 'passed'].includes(status))
    return 'border-emerald-600 text-emerald-400'
  if (status === 'awaiting_input')
    return 'border-sky-600 text-sky-300'
  if (status === 'partial')
    return 'border-amber-600 text-amber-300'
  if (status === 'running')
    return 'border-yellow-500 text-yellow-300'
  if (status === 'disputed')
    return 'border-rose-600 text-rose-400'
  if (status === 'unverifiable')
    return 'border-amber-600 text-amber-400'
  if (['failed', 'fail'].includes(status))
    return 'border-rose-600 text-rose-400'
  return 'border-zinc-700 text-zinc-500'
}

function titleClasses(status: string) {
  if (['completed', 'success', 'passed'].includes(status)) return 'text-emerald-300'
  if (status === 'awaiting_input') return 'text-sky-200'
  if (status === 'partial') return 'text-amber-200'
  if (status === 'running') return 'text-yellow-200'
  if (status === 'disputed') return 'text-rose-300'
  if (status === 'unverifiable') return 'text-amber-200'
  return 'text-zinc-200'
}
</script>

<template>
  <section
    class="rounded-lg border border-zinc-800 bg-zinc-900 p-3"
    :class="embedded ? 'border-0 bg-transparent p-0' : ''"
  >
    <h2
      v-if="!embedded"
      class="text-sm font-semibold text-zinc-100"
    >
      Plan checklist
    </h2>
    <div v-if="items.length" :class="embedded ? 'space-y-2' : 'mt-3 space-y-2'">
      <details
        v-for="item in items"
        :key="item.id"
        class="rounded-md border p-2 transition-colors"
        :class="rowClasses(String(item.status))"
        :open="item.status === 'running' || item.status === 'awaiting_input' || item.status === 'partial' || item.status === 'needs_revision' || item.status === 'disputed' || item.status === 'unverifiable'"
      >
        <summary class="flex cursor-pointer items-center gap-2 text-sm">
          <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border font-mono text-xs transition-colors"
            :class="checkboxClasses(String(item.status))"
          >
            {{ symbol(String(item.status)) }}
          </span>
          <span class="min-w-0 flex-1 font-medium" :class="titleClasses(String(item.status))">
            {{ item.title }}
          </span>
          <span class="rounded border px-1.5 py-0.5 text-[11px] text-zinc-400 border-zinc-700">{{ item.owner }}</span>
          <span
            class="rounded border px-1.5 py-0.5 font-mono text-[11px] transition-colors"
            :class="statusBadgeClasses(String(item.status))"
          >
            {{ item.status }}
          </span>
        </summary>
        <p v-if="item.description" class="mt-2 pl-7 text-sm text-zinc-400">
          {{ item.description }}
        </p>
      </details>
    </div>
    <UiEmptyState v-else title="No plan yet." hint="The orchestrator checklist will appear after planning." />
  </section>
</template>
