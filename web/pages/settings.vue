<script setup lang="ts">
definePageMeta({ layout: 'default' })
const base = useApiBase()
const { data, refresh } = await useFetch<Record<string, string>>(`${base}/api/settings`, { server: false })
const form = reactive<Record<string, string>>({})

// Allowed Ollama Cloud models only (must match app/config/bossku_models.php)
const ollamaCloudModels = [
  { label: 'Kimi K2.6 (Cloud)', value: 'kimi-k2.6' },
  { label: 'GLM 5.1 (Cloud)', value: 'glm-5.1' },
  { label: 'DeepSeek V4 Pro (Cloud)', value: 'deepseek-v4-pro' },
  { label: 'Qwen3 Coder Next (Cloud)', value: 'qwen3-coder-next' },
]

const ollamaEmbedModels = [
  { label: 'nomic-embed-text',      value: 'nomic-embed-text' },
  { label: 'nomic-embed-text v1.5', value: 'nomic-embed-text:v1.5' },
  { label: 'mxbai-embed-large',     value: 'mxbai-embed-large' },
  { label: 'all-minilm',            value: 'all-minilm' },
]

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

const showKey = ref(false)
const toast = useToast()

async function saveAndNotify() {
  try {
    await save()
    toast.success('Settings saved successfully.')
  }
  catch {
    toast.error('Failed to save settings.')
  }
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div>
      <h1 class="text-xl font-semibold text-zinc-100">Settings</h1>
      <p class="mt-1 text-sm text-zinc-400">
        Model backend: <span class="font-semibold text-emerald-400">Ollama</span>.
        API keys stay in <code class="text-zinc-300">app/.env</code> and are never exposed here.
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="saveAndNotify">
      <!-- Model selects -->
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase text-zinc-500 tracking-wider">Model Selection</h2>

        <label class="block text-sm text-zinc-300">
          Reasoning model (planner)
          <select v-model="form.planner_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
            <option v-for="m in ollamaCloudModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>

        <label class="block text-sm text-zinc-300">
          Coding model (executor)
          <select v-model="form.executor_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
            <option v-for="m in ollamaCloudModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>

        <label class="block text-sm text-zinc-300">
          Review / audit model
          <select v-model="form.auditor_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
            <option v-for="m in ollamaCloudModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>

        <label class="block text-sm text-zinc-300">
          Orchestrator model override
          <select v-model="form.orchestrator_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
            <option value="">— same as reasoning model —</option>
            <option v-for="m in ollamaCloudModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>

        <label class="block text-sm text-zinc-300">
          Embedding model
          <select v-model="form.embedding_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
            <option v-for="m in ollamaEmbedModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
      </div>

      <!-- Connection -->
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase text-zinc-500 tracking-wider">Connection</h2>

        <label class="block text-sm text-zinc-300">
          Ollama base URL
          <input
            v-model="form.ollama_base_url"
            class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none"
            placeholder="https://ollama.com"
          >
        </label>

        <label class="block text-sm text-zinc-300">
          Ollama API Key
          <div class="mt-1.5 relative">
            <input
              v-model="form.ollama_api_key"
              :type="showKey ? 'text' : 'password'"
              class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 pr-20 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none font-mono"
              placeholder="ollama_..."
              autocomplete="off"
            >
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 px-1"
              @click="showKey = !showKey"
            >
              {{ showKey ? 'Hide' : 'Show' }}
            </button>
          </div>
          <p class="mt-1 text-xs text-zinc-600">Get your key at <span class="text-zinc-400">ollama.com/settings/api-keys</span></p>
        </label>

        <label class="block text-sm text-zinc-300">
          Max memory results
          <input v-model="form.max_memory_results" type="number" min="1" max="50" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-emerald-500 focus:outline-none">
        </label>
      </div>

      <!-- Toggles -->
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-3">
        <h2 class="text-xs font-semibold uppercase text-zinc-500 tracking-wider">Features</h2>
        <label class="flex items-center gap-3 text-sm text-zinc-300 cursor-pointer">
          <input v-model="form.audit_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Audit step enabled
        </label>
        <label class="flex items-center gap-3 text-sm text-zinc-300 cursor-pointer">
          <input v-model="form.routing_llm_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Router LLM (disable for offline heuristics only)
        </label>
      </div>

      <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-600 transition-colors">
        Save settings
      </button>
    </form>
  </div>
</template>
