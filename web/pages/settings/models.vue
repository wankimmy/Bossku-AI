<script setup lang="ts">
definePageMeta({ layout: 'default' })

const base = useApiBase()
const { data, refresh, pending, error } = await useFetch<Record<string, string>>(`${base}/api/settings`, {
  server: false,
  lazy: true,
})

const form = reactive<Record<string, string>>({})
const aliasForm = reactive<Record<string, string>>({})
const ollamaApiKeyInput = ref('')
const ollamaApiKeyMasked = ref<string | null>(null)
const loaded = ref(false)

/** Keys accepted by PUT /api/settings (excludes read-only response fields). */
const SETTINGS_SAVE_KEYS = [
  'planner_model',
  'reasoning_model',
  'orchestrator_model',
  'router_model',
  'coding_model',
  'review_model',
  'auditor_model',
  'security_auditor_model',
  'final_reviewer_model',
  'writer_model',
  'direct_answer_model',
  'executor_default_model',
  'executor_frontend_model',
  'executor_backend_model',
  'executor_devops_model',
  'executor_high_risk_model',
  'embedding_model',
  'ollama_base_url',
  'max_memory_results',
  'max_revision_rounds',
  'audit_enabled',
  'memory_storage_enabled',
  'memory_ollama_enabled',
  'routing_llm_enabled',
] as const

const ollamaCloudModels = [
  { label: 'Kimi K2.6 (Cloud)', value: 'kimi-k2.6:cloud', logical: 'kimi-k2.6' },
  { label: 'GLM 5.1 (Cloud)', value: 'glm-5.1:cloud', logical: 'glm-5.1' },
  { label: 'DeepSeek V4 Pro (Cloud)', value: 'deepseek-v4-pro:cloud', logical: 'deepseek-v4-pro' },
  { label: 'Qwen3 Coder Next (Cloud)', value: 'qwen3-coder-next:cloud', logical: 'qwen3-coder-next' },
]

const ollamaEmbedModels = [
  { label: 'nomic-embed-text', value: 'nomic-embed-text' },
  { label: 'nomic-embed-text v1.5', value: 'nomic-embed-text:v1.5' },
  { label: 'mxbai-embed-large', value: 'mxbai-embed-large' },
  { label: 'all-minilm', value: 'all-minilm' },
]

const primaryAliasKeys = ['kimi-k2.6', 'glm-5.1', 'deepseek-v4-pro', 'qwen3-coder-next'] as const

function normalizeCloudModel(value: string | undefined): string {
  if (!value) return ''
  const trimmed = value.trim()
  if (ollamaCloudModels.some(m => m.value === trimmed)) return trimmed
  const tagged = trimmed.includes(':') ? trimmed : `${trimmed}:cloud`
  if (ollamaCloudModels.some(m => m.value === tagged)) return tagged
  return trimmed
}

function parseAliases(raw: string | undefined) {
  if (!raw) return
  try {
    const parsed = JSON.parse(raw) as Record<string, string>
    for (const key of primaryAliasKeys) {
      if (parsed[key]) aliasForm[key] = parsed[key]
    }
  }
  catch {
    //
  }
}

function applyCloudDefaults() {
  const fields = [
    'planner_model',
    'reasoning_model',
    'orchestrator_model',
    'router_model',
    'coding_model',
    'executor_model',
    'review_model',
    'auditor_model',
    'security_auditor_model',
    'final_reviewer_model',
    'writer_model',
    'direct_answer_model',
    'executor_default_model',
    'executor_frontend_model',
    'executor_backend_model',
    'executor_devops_model',
    'executor_high_risk_model',
  ] as const
  for (const key of fields) {
    if (form[key]) form[key] = normalizeCloudModel(form[key])
  }
}

const storedModelAliases = ref<Record<string, string>>({})

watch(data, (settings) => {
  if (!settings) return
  for (const key of SETTINGS_SAVE_KEYS) {
    const v = settings[key]
    if (v !== undefined && v !== null) form[key] = String(v)
  }
  storedModelAliases.value = {}
  if (settings.model_aliases) {
    try {
      storedModelAliases.value = JSON.parse(settings.model_aliases) as Record<string, string>
    }
    catch {
      //
    }
  }
  applyCloudDefaults()
  parseAliases(settings.model_aliases)
  ollamaApiKeyMasked.value = settings.ollama_api_key_masked ?? null
  ollamaApiKeyInput.value = ''
  for (const key of primaryAliasKeys) {
    if (!aliasForm[key] && ollamaCloudModels.some(m => m.logical === key)) {
      aliasForm[key] = `${key}:cloud`
    }
  }
  loaded.value = true
}, { immediate: true })

function settingsErrorMessage(err: unknown): string {
  if (err && typeof err === 'object' && 'data' in err) {
    const data = (err as { data?: { message?: string, errors?: Record<string, string[]> } }).data
    if (data?.message) return data.message
    if (data?.errors) {
      const first = Object.values(data.errors).flat()[0]
      if (first) return first
    }
  }
  return 'Failed to save settings.'
}

async function save() {
  const aliases: Record<string, string> = { ...storedModelAliases.value }
  for (const key of primaryAliasKeys) {
    const v = aliasForm[key]?.trim()
    if (v) aliases[key] = v
  }

  const body: Record<string, string | number | Record<string, string>> = {
    model_aliases: aliases,
  }
  for (const key of SETTINGS_SAVE_KEYS) {
    const v = form[key]
    if (v !== undefined && v !== '') body[key] = v
  }
  const keyTrimmed = ollamaApiKeyInput.value.trim()
  if (keyTrimmed) body.ollama_api_key = keyTrimmed

  await $fetch(`${base}/api/settings`, {
    method: 'PUT',
    body,
  })
  ollamaApiKeyInput.value = ''
  await refresh()
}

const showKey = ref(false)
const toast = useToast()

async function saveAndNotify() {
  try {
    await save()
    toast.success('Settings saved — new runs use these models immediately.')
  }
  catch (err: unknown) {
    toast.error(settingsErrorMessage(err))
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <p class="text-sm text-zinc-500">
      Agent models are stored in the database and edited here. Workspace paths stay in
      <code class="text-zinc-400">app/.env</code>; Ollama API key is optional below (or via
      <code class="text-zinc-400">OLLAMA_API_KEY</code> in env for local Ollama).
    </p>

    <p v-if="pending && !loaded" class="text-sm text-zinc-500">
      Loading settings…
    </p>
    <p v-else-if="error" class="text-sm text-rose-400">
      Could not load settings. Check that the API is running at {{ base }}.
    </p>

    <form v-else class="space-y-4" @submit.prevent="saveAndNotify">
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Fast (router &amp; direct answer)
        </h2>
        <label class="block text-sm text-zinc-300">
          Router model
          <select v-model="form.router_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'r-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Direct answer model
          <select v-model="form.direct_answer_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'d-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Reasoning (planner / orchestrator / writer / final)
        </h2>
        <label class="block text-sm text-zinc-300">
          Reasoning model (planner)
          <select v-model="form.reasoning_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'p-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Orchestrator override
          <select v-model="form.orchestrator_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'o-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Writer model
          <select v-model="form.writer_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'w-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Final reviewer model
          <select v-model="form.final_reviewer_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'f-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Coding (executor profiles)
        </h2>
        <label class="block text-sm text-zinc-300">
          Default coding model
          <select v-model="form.coding_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'c-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Executor — default profile
          <select v-model="form.executor_default_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'ed-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Executor — frontend UI
          <select v-model="form.executor_frontend_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'ef-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Executor — backend
          <select v-model="form.executor_backend_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'eb-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Executor — DevOps
          <select v-model="form.executor_devops_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'edo-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Executor — high risk
          <select v-model="form.executor_high_risk_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'eh-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Review (auditor &amp; security)
        </h2>
        <label class="block text-sm text-zinc-300">
          Review model (shared default)
          <select v-model="form.review_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'rv-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Auditor model
          <select v-model="form.auditor_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'a-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Security auditor model
          <select v-model="form.security_auditor_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaCloudModels" :key="'sa-'+m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Cloud aliases (logical → Ollama tag)
        </h2>
        <p class="text-xs text-zinc-600">
          Maps short names like <code class="text-zinc-400">kimi-k2.6</code> to
          <code class="text-zinc-400">kimi-k2.6:cloud</code> at runtime.
        </p>
        <label
          v-for="key in primaryAliasKeys"
          :key="key"
          class="block text-sm text-zinc-300"
        >
          {{ key }}
          <input
            v-model="aliasForm[key]"
            class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100"
            :placeholder="`${key}:cloud`"
          >
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Connection &amp; memory
        </h2>
        <label class="block text-sm text-zinc-300">
          Ollama base URL
          <input
            v-model="form.ollama_base_url"
            class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
            placeholder="https://ollama.com"
          >
        </label>
        <label class="block text-sm text-zinc-300">
          Ollama API key
          <span class="text-zinc-600 font-normal">(optional)</span>
          <div class="mt-1.5 flex gap-2">
            <input
              v-model="ollamaApiKeyInput"
              :type="showKey ? 'text' : 'password'"
              autocomplete="off"
              class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100"
              :placeholder="ollamaApiKeyMasked ? `Saved (${ollamaApiKeyMasked}) — leave blank to keep` : 'Leave blank for local Ollama without auth'"
            >
            <button
              type="button"
              class="shrink-0 rounded-md border border-zinc-700 px-3 py-2 text-xs text-zinc-400 hover:text-zinc-200"
              @click="showKey = !showKey"
            >
              {{ showKey ? 'Hide' : 'Show' }}
            </button>
          </div>
        </label>
        <label class="block text-sm text-zinc-300">
          Embedding model
          <select v-model="form.embedding_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaEmbedModels" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="block text-sm text-zinc-300">
          Max memory results
          <input
            v-model="form.max_memory_results"
            type="number"
            min="1"
            max="50"
            class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
          >
        </label>
        <p class="text-xs text-zinc-600">
          Required only for Ollama Cloud (<code class="text-zinc-400">https://ollama.com</code>).
          Local Ollama at <code class="text-zinc-400">http://host.docker.internal:11434</code> can leave this empty.
        </p>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Features
        </h2>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-zinc-300">
          <input v-model="form.audit_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Audit step enabled
        </label>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-zinc-300">
          <input v-model="form.routing_llm_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Router LLM (disable for offline heuristics only)
        </label>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-zinc-300">
          <input v-model="form.memory_storage_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Memory storage enabled
        </label>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-zinc-300">
          <input v-model="form.memory_ollama_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
          Memory embeddings via Ollama
        </label>
      </div>

      <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-600">
        Save settings
      </button>
    </form>
  </div>
</template>
