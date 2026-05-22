<script setup lang="ts">
definePageMeta({ layout: 'default' })
const { data, pending } = await useFetch(apiUrl('/checklists'), { server: false })
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">
      Checklists
    </h1>
    <UiSkeleton v-if="pending" class="h-32 w-full" />
    <ul v-else class="divide-y rounded-xl border dark:divide-zinc-800 dark:border-zinc-800">
      <li v-for="c in data?.data" :key="c.id">
        <NuxtLink class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/60" :to="`/checklists/${c.id}`">
          <p class="font-medium">
            {{ c.name }}
          </p>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
