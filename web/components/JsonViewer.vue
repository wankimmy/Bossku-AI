<script setup lang="ts">
defineProps<{ data: unknown; class?: string }>()
function pretty(d: unknown) {
  try {
    return JSON.stringify(d, null, 2)
  }
  catch {
    return String(d)
  }
}
</script>

<template>
  <pre
    class="overflow-auto rounded-md bg-zinc-100 p-3 font-mono text-xs text-zinc-800 dark:bg-zinc-950 dark:text-zinc-300"
    :class="class"
  >{{ pretty(data) }}</pre>
</template>
