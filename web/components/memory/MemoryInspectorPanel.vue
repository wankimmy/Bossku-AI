<script setup lang="ts">
const q = ref('')
const results = ref<unknown[]>([])
const searching = ref(false)

async function search() {
  searching.value = true
  try {
    const res = await $fetch<unknown[]>(apiUrl('/memory/search'), {
      method: 'POST',
      body: { query: q.value, top_k: 8 },
    })

    results.value = Array.isArray(res) ? res : []
  }
  finally {
    searching.value = false
  }
}

const { data, pending, refresh } = await useFetch(apiUrl('/memory'), { server: false })
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-2">
    <div class="space-y-4">
      <h2 class="text-lg font-semibold">
        Memory inspector
      </h2>
      <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="search">
        <input v-model="q" class="min-w-0 flex-1 rounded-lg border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900" placeholder="Semantic search">
        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white dark:bg-zinc-100 dark:text-zinc-900" :disabled="searching">
          Search
        </button>
      </form>
      <UiEmptyState v-if="results.length === 0 && !searching" title="No search results." hint="Try a query or browse stored memory on the right." />
      <ul v-else class="space-y-2">
        <li v-for="(m, i) in results" :key="i" class="rounded-lg border p-3 text-sm dark:border-zinc-800">
          <JsonViewer :data="m" />
        </li>
      </ul>
    </div>
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        Stored memory
      </h2>
      <UiSkeleton v-if="pending" class="h-40 w-full" />
      <UiEmptyState v-else-if="!data?.data?.length" title="No memory stored yet." hint="Durable learnings will appear here after runs with memory storage enabled." />
      <ul v-else class="space-y-2">
        <li v-for="m in data.data" :key="m.id" class="rounded-lg border p-3 dark:border-zinc-800">
          <p class="text-sm font-medium">
            {{ m.human_summary || m.content?.slice(0, 120) }}
          </p>
          <p class="text-xs text-zinc-500">
            {{ m.type }} · {{ m.source || '—' }}
          </p>
        </li>
      </ul>
      <button type="button" class="text-sm underline" @click="refresh()">
        Refresh list
      </button>
    </div>
  </div>
</template>
