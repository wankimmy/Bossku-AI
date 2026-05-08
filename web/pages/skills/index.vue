<script setup lang="ts">
definePageMeta({ layout: 'default' })
const base = useApiBase()
const q = ref('')
const uri = computed(() => {
  const u = new URL(`${base}/api/skills`)
  if (q.value) u.searchParams.set('q', q.value)

  return u.toString()
})
const { data, pending } = await useFetch(() => uri.value, { server: false })
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-xl font-semibold">
          Skills
        </h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
          Imported from SKILL.md metadata; links to matched playbooks and checklists.
        </p>
      </div>
      <label class="text-sm"><span class="sr-only">Search</span><input v-model="q" placeholder="Search" class="w-full rounded-lg border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900 sm:w-64"></label>
    </div>
    <UiSkeleton v-if="pending" class="h-32 w-full" />
    <UiEmptyState v-else-if="!data?.data?.length" title="No skills imported yet." hint="Run knowledge import first: php artisan bosskuai:import-knowledge --fresh" />
    <ul v-else class="divide-y divide-zinc-200 rounded-xl border dark:divide-zinc-800 dark:border-zinc-800">
      <li v-for="s in data.data" :key="s.id">
        <NuxtLink class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/60" :to="`/skills/${s.id}`">
          <div>
            <p class="font-medium">
              {{ s.name }}
            </p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
              {{ s.description }}
            </p>
          </div>
          <span class="rounded border px-2 py-0.5 text-xs">{{ s.source_path }}</span>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
