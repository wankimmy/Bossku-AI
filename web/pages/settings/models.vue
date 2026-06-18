<script setup lang="ts">
import { AGENT_ROLE_DEFS, defaultProviderForRole } from '~/composables/useRoleModelDefaults'

definePageMeta({ layout: 'default' })

const route = useRoute()
const { data, refresh, pending, error } = await useFetch<Record<string, string>>(apiUrl('/settings'), {
  server: false,
  lazy: true,
})

const { optgroups, refresh: refreshCatalog, catalog: inferenceCatalog, providerGroups, applyRecommendedDefaults } = useInferenceCatalog()

type CodexStatus = {
  connected: boolean
  configured: boolean
  expires_at: string | null
  account_hint: string | null
  last_refresh: string | null
}

const codexStatus = ref<CodexStatus | null>(null)
const codexStatusLoading = ref(true)

async function loadCodexStatus() {
  codexStatusLoading.value = true
  try {
    codexStatus.value = await $fetch<CodexStatus>(apiUrl('/oauth/codex/status'))
  }
  catch {
    codexStatus.value = { connected: false, configured: false, expires_at: null, account_hint: null, last_refresh: null }
  }
  finally {
    codexStatusLoading.value = false
  }
}

await loadCodexStatus()

const form = reactive<Record<string, string>>({})
const aliasForm = reactive<Record<string, string>>({})
const ollamaApiKeyInput = ref('')
const ollamaApiKeyMasked = ref<string | null>(null)
const anthropicApiKeyInput = ref('')
const anthropicApiKeyMasked = ref<string | null>(null)
const anthropicConfigured = ref(false)
const loaded = ref(false)

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
  'orchestrator_plan_confirmation_mode',
] as const

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
  const ollamaIds = (inferenceCatalog.value?.ollama ?? []).map(m => m.id)
  if (ollamaIds.includes(trimmed)) return trimmed
  const tagged = trimmed.includes(':') ? trimmed : `${trimmed}:cloud`
  if (ollamaIds.includes(tagged)) return tagged
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

const providerByRole = reactive<Record<string, string>>({})

function initProviderSelections() {
  const groups = providerGroups.value
  for (const def of AGENT_ROLE_DEFS) {
    if (!providerByRole[def.role]) {
      providerByRole[def.role] = defaultProviderForRole(groups, def.role)
    }
  }
}

watch(providerGroups, initProviderSelections, { immediate: true })

async function applyCloudDefaults() {
  try {
    const applied = await applyRecommendedDefaults()
    for (const [role, entry] of Object.entries(applied)) {
      providerByRole[role] = entry.provider
      const def = AGENT_ROLE_DEFS.find(d => d.role === role)
      if (def) form[def.formKey] = entry.model
    }
    toast.success('Applied recommended cloud models for configured providers.')
  }
  catch (err: unknown) {
    toast.error(settingsErrorMessage(err))
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
  anthropicApiKeyMasked.value = settings.anthropic_api_key_masked ?? null
  anthropicApiKeyInput.value = ''
  anthropicConfigured.value = settings.anthropic_configured === '1'
  for (const key of primaryAliasKeys) {
    if (!aliasForm[key]) {
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
  if (form.orchestrator_model) body.reasoning_model = form.orchestrator_model
  if (form.coding_model) {
    body.executor_default_model = form.coding_model
    body.executor_frontend_model = form.coding_model
    body.executor_backend_model = form.coding_model
    body.executor_devops_model = form.coding_model
    body.executor_high_risk_model = form.coding_model
  }
  if (form.auditor_model) body.review_model = form.auditor_model
  const ollamaTrimmed = ollamaApiKeyInput.value.trim()
  if (ollamaTrimmed) body.ollama_api_key = ollamaTrimmed
  const anthropicTrimmed = anthropicApiKeyInput.value.trim()
  if (anthropicTrimmed) body.anthropic_api_key = anthropicTrimmed

  await $fetch(apiUrl('/settings'), {
    method: 'PUT',
    body,
  })
  ollamaApiKeyInput.value = ''
  anthropicApiKeyInput.value = ''
  await refresh()
  await refreshCatalog()
}

const showOllamaKey = ref(false)
const showAnthropicKey = ref(false)
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

function connectCodex() {
  if (codexStatus.value && !codexStatus.value.configured) {
    toast.error('Codex OAuth is not configured. Set CODEX_OAUTH_CLIENT_ID in the server environment.')
    return
  }
  window.location.href = apiUrl('/oauth/codex/authorize')
}

async function disconnectCodex() {
  try {
    await $fetch(apiUrl('/oauth/codex'), { method: 'DELETE' })
    await loadCodexStatus()
    await refreshCatalog()
    toast.success('Codex disconnected.')
  }
  catch (err: unknown) {
    toast.error(settingsErrorMessage(err))
  }
}

onMounted(() => {
  const codex = route.query.codex
  if (codex === 'connected') {
    toast.success('Codex connected with ChatGPT.')
    loadCodexStatus()
    refreshCatalog()
  }
  else if (codex === 'error') {
    const message = typeof route.query.message === 'string' ? route.query.message : 'Codex connection failed.'
    toast.error(message)
  }
})
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <p class="text-sm text-zinc-500">
      Cloud-only model routing. Pick a provider per agent, then choose from the best recommended models for that role.
      API keys live in <NuxtLink to="/settings/providers" class="text-emerald-400 hover:underline">Settings → Providers</NuxtLink>.
      Codex uses ChatGPT OAuth below.
    </p>

    <div v-if="loaded" class="flex flex-wrap gap-2">
      <button
        type="button"
        class="rounded-md border border-emerald-800 bg-emerald-900/40 px-4 py-2 text-sm text-emerald-300 hover:bg-emerald-800/40"
        @click="applyCloudDefaults"
      >
        Apply recommended cloud defaults
      </button>
    </div>

    <p v-if="pending && !loaded" class="text-sm text-zinc-500">
      Loading settings…
    </p>
    <p v-else-if="error" class="text-sm text-rose-400">
      Could not load settings. Check that the API is running at {{ base }}.
    </p>

    <form v-else class="space-y-4" @submit.prevent="saveAndNotify">
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Anthropic
        </h2>
        <p class="text-xs text-zinc-600">
          Required for Claude models in the dropdowns.
          <a
            href="https://console.anthropic.com/settings/keys"
            target="_blank"
            rel="noopener noreferrer"
            class="text-emerald-600 hover:text-emerald-500"
          >Get an API key</a>.
        </p>
        <label class="block text-sm text-zinc-300">
          Anthropic API key
          <div class="mt-1.5 flex gap-2">
            <input
              v-model="anthropicApiKeyInput"
              :type="showAnthropicKey ? 'text' : 'password'"
              autocomplete="off"
              class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100"
              :placeholder="anthropicApiKeyMasked ? `Saved (${anthropicApiKeyMasked}) — leave blank to keep` : 'sk-ant-…'"
            >
            <button
              type="button"
              class="shrink-0 rounded-md border border-zinc-700 px-3 py-2 text-xs text-zinc-400 hover:text-zinc-200"
              @click="showAnthropicKey = !showAnthropicKey"
            >
              {{ showAnthropicKey ? 'Hide' : 'Show' }}
            </button>
          </div>
        </label>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Codex (ChatGPT)
        </h2>
        <p v-if="codexStatusLoading" class="text-xs text-zinc-600">
          Checking connection…
        </p>
        <template v-else>
          <p v-if="!codexStatus?.configured" class="text-xs text-amber-500/90">
            OAuth is not configured on the server. Set <code class="text-zinc-400">CODEX_OAUTH_CLIENT_ID</code> (and matching redirect URI) in <code class="text-zinc-400">app/.env</code>.
          </p>
          <p v-else-if="codexStatus?.connected" class="text-sm text-emerald-400">
            Connected
            <span v-if="codexStatus.account_hint" class="text-zinc-500">({{ codexStatus.account_hint }})</span>
            <span v-if="codexStatus.expires_at" class="block text-xs text-zinc-600">
              Token expires {{ codexStatus.expires_at }}
            </span>
          </p>
          <p v-else class="text-sm text-zinc-400">
            Not connected — sign in with ChatGPT to use Codex models.
          </p>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="!codexStatus?.connected"
              type="button"
              class="rounded-md bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-white"
              :disabled="!codexStatus?.configured"
              @click="connectCodex"
            >
              Connect with ChatGPT
            </button>
            <button
              v-else
              type="button"
              class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:border-zinc-600"
              @click="disconnectCodex"
            >
              Disconnect
            </button>
          </div>
        </template>
      </div>

      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 space-y-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
          Agent models (cloud providers)
        </h2>
        <p class="text-xs text-zinc-600">
          Select provider first. Model dropdown shows only the best cloud models for each agent role.
        </p>
        <ProvidersAgentRoleModelRow
          v-for="def in AGENT_ROLE_DEFS"
          :key="def.role"
          :label="def.label"
          :role="def.role"
          :model-value="form[def.formKey] ?? ''"
          :provider-value="providerByRole[def.role] ?? ''"
          :groups="providerGroups"
          @update:model-value="form[def.formKey] = $event"
          @update:provider-value="providerByRole[def.role] = $event"
        />
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
              :type="showOllamaKey ? 'text' : 'password'"
              autocomplete="off"
              class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-zinc-100"
              :placeholder="ollamaApiKeyMasked ? `Saved (${ollamaApiKeyMasked}) — leave blank to keep` : 'Leave blank for local Ollama without auth'"
            >
            <button
              type="button"
              class="shrink-0 rounded-md border border-zinc-700 px-3 py-2 text-xs text-zinc-400 hover:text-zinc-200"
              @click="showOllamaKey = !showOllamaKey"
            >
              {{ showOllamaKey ? 'Hide' : 'Show' }}
            </button>
          </div>
        </label>
        <label class="block text-sm text-zinc-300">
          Embedding model
          <select v-model="form.embedding_model" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option v-for="m in ollamaEmbedModels" :key="m.value" :value="m.value">
              {{ m.label }}
            </option>
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
          Ollama key is required for Ollama Cloud. Claude models need an Anthropic key; Codex models need ChatGPT connection above.
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
        <label class="block text-sm text-zinc-300">
          Plan Mode
          <select v-model="form.orchestrator_plan_confirmation_mode" class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option value="always">Always review master plan before execution</option>
            <option value="questions">Only pause when planner asks questions</option>
            <option value="off">Off</option>
          </select>
        </label>
      </div>

      <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-600">
        Save settings
      </button>
    </form>
  </div>
</template>
