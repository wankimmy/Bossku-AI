<script setup lang="ts">
definePageMeta({ layout: 'default' })
const editing = ref<Record<string, string>>({})

const { data, pending, refresh } = await useFetch<{
  data: Array<{ id: string; name: string; rule_text: string; scope: string; priority: number }>
}>(apiUrl('/rules'), { server: false })

watch(() => data.value?.data, (rows) => {
  if (!rows) return

  rows.forEach((r) => {
    if (!(r.id in editing.value)) editing.value[r.id] = r.rule_text
  })
})

async function save(id: string) {
  await $fetch(apiUrl(`/rules/${id}`), {
    method: 'PATCH',
    body: { rule_text: editing.value[id] },
  })
  await refresh()
}
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">
      Rules
    </h1>
    <p class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
      Editing here changes runtime behavior in the database. It does not modify the source markdown file.
    </p>
    <UiSkeleton v-if="pending" class="h-40 w-full" />
    <div v-else class="space-y-4">
      <div v-for="r in data?.data" :key="r.id" class="rounded-xl border p-4 dark:border-zinc-800">
        <div class="mb-2 flex flex-wrap justify-between gap-2 text-sm">
          <span class="font-medium">{{ r.name }}</span>
          <span class="text-zinc-500">scope: {{ r.scope }} · priority {{ r.priority }}</span>
        </div>
        <textarea v-model="editing[r.id]" class="min-h-[100px] w-full rounded border px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
        <button type="button" class="mt-2 rounded bg-zinc-900 px-3 py-1.5 text-sm text-white dark:bg-zinc-100 dark:text-zinc-900" @click="save(r.id)">
          Save
        </button>
      </div>
    </div>
  </div>
</template>
