<script setup lang="ts">
import type { PlanChecklistItem } from '../types/bossku'

defineProps<{ items: PlanChecklistItem[] }>()

function symbol(status: string) {
  if (['completed', 'success', 'passed'].includes(status)) return '✓'
  if (status === 'running') return '…'
  if (status === 'needs_revision') return '!'
  if (['failed', 'fail'].includes(status)) return '×'
  if (status === 'skipped') return '-'
  return '○'
}
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <h2 class="text-sm font-semibold">
      Plan checklist
    </h2>
    <div v-if="items.length" class="mt-3 space-y-2">
      <details
        v-for="item in items"
        :key="item.id"
        class="rounded-md border border-zinc-200 p-2 dark:border-zinc-800"
        :open="item.status === 'running' || item.status === 'needs_revision'"
      >
        <summary class="flex cursor-pointer items-center gap-2 text-sm">
          <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-zinc-300 font-mono text-xs dark:border-zinc-700">
            {{ symbol(String(item.status)) }}
          </span>
          <span class="min-w-0 flex-1 font-medium">{{ item.title }}</span>
          <span class="rounded border border-zinc-300 px-1.5 py-0.5 text-[11px] dark:border-zinc-700">{{ item.owner }}</span>
          <span class="font-mono text-[11px] text-zinc-500">{{ item.status }}</span>
        </summary>
        <p v-if="item.description" class="mt-2 pl-7 text-sm text-zinc-600 dark:text-zinc-400">
          {{ item.description }}
        </p>
      </details>
    </div>
    <UiEmptyState v-else title="No plan yet." hint="The orchestrator checklist will appear after planning." />
  </section>
</template>
