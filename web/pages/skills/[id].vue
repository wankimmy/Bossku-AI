<script setup lang="ts">
definePageMeta({ layout: 'default' })
const route = useRoute()
const base = useApiBase()
const { data, pending } = await useFetch(() => `${base}/api/skills/${route.params.id}`, { server: false })
</script>

<template>
  <UiSkeleton v-if="pending" class="h-64 w-full" />
  <div v-else-if="data" class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl border p-4 dark:border-zinc-800">
      <h1 class="text-xl font-semibold">
        {{ data.name }}
      </h1>
      <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ data.description }}
      </p>
      <p class="mt-2 font-mono text-xs text-zinc-500">
        {{ data.source_path }}
      </p>
    </div>
    <div class="rounded-xl border p-4 dark:border-zinc-800">
      <h2 class="text-sm font-semibold mb-2">
        Source markdown
      </h2>
      <pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap text-xs">{{ data.content }}</pre>
    </div>
  </div>
</template>
