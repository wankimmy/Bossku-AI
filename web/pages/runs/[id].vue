<script setup lang="ts">
definePageMeta({ layout: 'default' })
const route = useRoute()
const base = useApiBase()
const { data, pending } = await useFetch(() => `${base}/api/runs/${route.params.id}`, { server: false })
</script>

<template>
  <UiSkeleton v-if="pending" class="h-64 w-full" />
  <div v-else-if="data" class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold">
        Run detail
      </h1>
      <p class="mt-2 whitespace-pre-wrap rounded-lg border bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
        {{ data.prompt }}
      </p>
    </div>
    <RoutingDashboard :metadata="data.metadata" />
    <div v-if="data.final_output">
      <h2 class="text-sm font-semibold">
        Final response
      </h2>
      <div class="mt-2 whitespace-pre-wrap text-sm">
        {{ data.final_output }}
      </div>
    </div>
    <div>
      <h2 class="mb-2 text-sm font-semibold">
        Steps (raw)
      </h2>
      <div class="space-y-2">
        <JsonViewer v-for="s in data.steps" :key="s.id" :data="s" />
      </div>
    </div>
  </div>
</template>
