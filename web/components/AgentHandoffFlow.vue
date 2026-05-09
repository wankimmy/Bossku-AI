<script setup lang="ts">
import type { HandoffNode } from '../types/bossku'

defineProps<{ nodes: HandoffNode[] }>()

function statusClass(status: string) {
  if (status === 'running') return 'border-emerald-500 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
  if (['completed', 'success', 'passed'].includes(status)) return 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100'
  if (status === 'needs_revision') return 'border-amber-400 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
  if (['failed', 'fail'].includes(status)) return 'border-rose-400 bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300'
  return 'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400'
}
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <h2 class="mb-3 text-sm font-semibold">
      Agent workflow
    </h2>
    <ol class="flex gap-2 overflow-x-auto pb-1" aria-label="Agent handoff flow">
      <li v-for="(node, idx) in nodes" :key="`${node.agent}-${idx}`" class="flex min-w-fit items-center gap-2">
        <div
          class="rounded-md border px-2.5 py-1.5 text-xs"
          :class="[statusClass(String(node.status)), node.status === 'running' ? 'animate-pulse' : '']"
        >
          <div class="font-medium">
            {{ node.label }}
          </div>
          <div class="mt-0.5 font-mono">
            {{ node.status }}
          </div>
        </div>
        <span v-if="idx < nodes.length - 1" class="text-zinc-400">→</span>
      </li>
    </ol>
  </section>
</template>
