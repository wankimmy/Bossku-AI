<script setup lang="ts">
definePageMeta({ layout: 'default' })
const base = useApiBase()
const { data, refresh } = await useFetch<Record<string, string>>(`${base}/api/settings`, { server: false })
const form = reactive<Record<string, string>>({})

watchEffect(() => {
  if (data.value) {
    Object.assign(form, data.value)
    form.planner_provider = 'ollama'
    form.auditor_provider = 'ollama'
  }
})

async function save() {
  await $fetch(`${base}/api/settings`, {
    method: 'PUT',
    body: {
      ...form,
      planner_provider: 'ollama',
      auditor_provider: 'ollama',
    },
  })
  await refresh()
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div>
      <h1 class="text-xl font-semibold">
        Settings
      </h1>
      <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        BosskuAI runtime is Ollama-only. API keys stay in backend <code>app/.env</code> and are never exposed here.
      </p>
    </div>
    <form class="space-y-3" @submit.prevent="save">
      <label class="block text-sm">
        Planner provider
        <input
          value="ollama"
          readonly
          class="mt-1 w-full cursor-not-allowed rounded border bg-zinc-100 px-3 py-2 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
        >
      </label>
      <label class="block text-sm">
        Planner model
        <input v-model="form.planner_model" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="block text-sm">
        Auditor provider
        <input
          value="ollama"
          readonly
          class="mt-1 w-full cursor-not-allowed rounded border bg-zinc-100 px-3 py-2 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
        >
      </label>
      <label class="block text-sm">
        Auditor model
        <input v-model="form.auditor_model" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="block text-sm">
        Executor model (Ollama)
        <input v-model="form.executor_model" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="block text-sm">
        Embedding model (Ollama)
        <input v-model="form.embedding_model" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="block text-sm">
        Ollama base URL
        <input v-model="form.ollama_base_url" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="block text-sm">
        Max memory results
        <input v-model="form.max_memory_results" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.audit_enabled" type="checkbox" true-value="1" false-value="0"> Audit enabled
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.routing_llm_enabled" type="checkbox" true-value="1" false-value="0"> Router LLM (disable for offline heuristics only)
      </label>
      <label class="block text-sm">
        Orchestrator model override
        <input v-model="form.orchestrator_model" placeholder="optional" class="mt-1 w-full rounded border px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
      </label>
      <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white dark:bg-zinc-100 dark:text-zinc-900">
        Save settings
      </button>
    </form>
  </div>
</template>
