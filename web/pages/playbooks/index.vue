<script setup lang="ts">
definePageMeta({ layout: 'default' })
const { data, pending } = await useFetch(apiUrl('/playbooks'), { server: false })
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">
      Playbooks
    </h1>
    <UiSkeleton v-if="pending" class="h-32 w-full" />
    <ul v-else class="divide-y rounded-xl border dark:divide-zinc-800 dark:border-zinc-800">
      <li v-for="p in data?.data" :key="p.id">
        <NuxtLink class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/60" :to="`/playbooks/${p.id}`">
          <p class="font-medium">
            {{ p.name }}
          </p>
          <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ p.description }}
          </p>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
