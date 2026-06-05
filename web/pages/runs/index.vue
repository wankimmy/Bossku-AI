<script setup lang="ts">
definePageMeta({ layout: 'default' })
const { data, pending } = await useFetch(apiUrl('/runs'), { server: false })
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">
      Run history
    </h1>
    <UiEmptyState v-if="!pending && !data?.data?.length" title="No runs yet." hint="Start from the main chat." />
    <UiSkeleton v-if="pending" class="h-40 w-full" />
    <ul v-else class="divide-y rounded-xl border dark:divide-zinc-800 dark:border-zinc-800">
      <li v-for="r in data?.data" :key="r.id">
        <NuxtLink class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/60" :to="`/runs/${r.id}`">
          <p class="line-clamp-2 text-sm font-medium">
            {{ r.prompt }}
          </p>
          <p class="mt-1 flex flex-wrap gap-2 text-xs text-zinc-500">
            <span>{{ r.status }}</span>
            <span v-if="r.total_latency_ms">{{ r.total_latency_ms }}ms</span>
            <span>{{ r.created_at }}</span>
          </p>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
