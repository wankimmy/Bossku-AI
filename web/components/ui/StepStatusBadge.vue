<script setup lang="ts">
defineProps<{ status: string }>()
</script>

<template>
  <span
    v-if="!status"
    class="rounded border border-zinc-300 px-2 py-0.5 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-400"
  >–</span>
  <span
    v-else-if="status === 'success'"
    class="rounded border border-emerald-300 px-2 py-0.5 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
  >Success</span>
  <span
    v-else-if="status === 'fail' || status === 'failed'"
    class="rounded border border-rose-300 px-2 py-0.5 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
  >Failed</span>
  <span
    v-else-if="status === 'running'"
    class="rounded border border-amber-300 px-2 py-0.5 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
  >Running…</span>
  <span
    v-else
    class="rounded border border-zinc-300 px-2 py-0.5 text-xs dark:border-zinc-700"
  >{{ status }}</span>
</template>
