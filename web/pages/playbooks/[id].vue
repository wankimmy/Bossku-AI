<script setup lang="ts">
definePageMeta({ layout: 'default' })
const route = useRoute()
const base = useApiBase()
const { data, pending } = await useFetch(() => `${base}/api/playbooks/${route.params.id}`, { server: false })
</script>

<template>
  <UiSkeleton v-if="pending" class="h-64 w-full" />
  <div v-else-if="data" class="space-y-4">
    <h1 class="text-xl font-semibold">
      {{ data.name }}
    </h1>
    <pre class="whitespace-pre-wrap rounded-xl border p-4 text-sm dark:border-zinc-800">{{ data.content }}</pre>
  </div>
</template>
