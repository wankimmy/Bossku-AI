<script setup lang="ts">
import type { LlmProvider } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending, error, refresh } = await useAsyncData<LlmProvider[]>(
  'providers',
  () => api.get('/providers'),
  { server: false, lazy: true },
)

const { data: presets } = await useAsyncData('provider-presets', () => api.get('/providers/presets'), { server: false, lazy: true })

const providers = computed<LlmProvider[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: LlmProvider[] }).data ?? []
})

const healthCls = (h: string) => {
  switch (h) {
    case 'healthy': return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
    case 'degraded': return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
    case 'offline': return 'bg-red-900/50 text-red-300 border-red-800'
    default: return 'bg-zinc-800 text-zinc-400 border-zinc-700'
  }
}

const toast = useToast()
const testingId = ref<string | null>(null)
const syncingId = ref<string | null>(null)

async function testProvider(id: string) {
  testingId.value = id
  try {
    await api.post(`/providers/${id}/test`)
    toast.success('Connection test passed.')
  }
  catch {
    toast.error('Connection test failed.')
  }
  finally {
    testingId.value = null
    await refresh()
  }
}

async function syncModels(id: string) {
  syncingId.value = id
  try {
    const res = await api.post<{ synced?: number, message?: string }>(`/providers/${id}/sync-models`)
    toast.success(`Synced ${res?.synced ?? 0} cloud models.`)
  }
  catch {
    toast.error('Failed to sync models.')
  }
  finally {
    syncingId.value = null
    await refresh()
  }
}

async function toggleActive(provider: LlmProvider) {
  try {
    await api.patch(`/providers/${provider.id}`, { is_active: !provider.is_active })
    toast.success(`Provider ${provider.is_active ? 'disabled' : 'enabled'}.`)
    await refresh()
  }
  catch {
    toast.error('Failed to update provider.')
  }
}

const showAddForm = ref(false)
const newProvider = reactive({ name: '', type: 'openai_compatible', slug: '', base_url: '', api_key: '' })

const presetOptions = computed(() => {
  const p = presets.value
  if (!p) return []
  return Array.isArray(p) ? p : []
})

function applyPreset(slug: string) {
  const preset = presetOptions.value.find((p: { slug?: string }) => p.slug === slug)
  if (!preset) return
  newProvider.name = preset.name ?? slug
  newProvider.slug = slug
  newProvider.type = preset.type ?? 'openai_compatible'
  newProvider.base_url = preset.base_url ?? ''
}

async function addProvider() {
  if (newProvider.type === 'codex_oauth') {
    toast.error('Codex uses OAuth. Connect in Settings → Models.')
    return
  }
  try {
    await api.post('/providers', {
      name: newProvider.name,
      slug: newProvider.slug || newProvider.name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
      type: newProvider.type,
      base_url: newProvider.base_url || undefined,
      api_key: newProvider.api_key || undefined,
      is_active: true,
    })
    toast.success(`Provider "${newProvider.name}" added.`)
    showAddForm.value = false
    Object.assign(newProvider, { name: '', type: 'openai_compatible', slug: '', base_url: '', api_key: '' })
    await refresh()
  }
  catch {
    toast.error('Failed to add provider.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-lg border border-zinc-700 bg-zinc-900/80 px-4 py-3 text-sm text-zinc-400">
      <strong class="text-zinc-300">Cloud provider registry.</strong>
      Add API keys for DeepSeek, Kimi, GLM, Qwen, OpenAI, Anthropic, Ollama Cloud, and more.
      Codex uses ChatGPT OAuth in <NuxtLink to="/settings/models" class="underline text-zinc-300">Settings → Models</NuxtLink>.
    </div>

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-zinc-100">LLM Providers</h1>
        <p class="text-sm text-zinc-500 mt-1">Cloud API keys and provider health.</p>
      </div>
      <button
        type="button"
        class="px-3 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50"
        @click="showAddForm = !showAddForm"
      >
        + Add Provider
      </button>
    </div>

    <div v-if="showAddForm" class="rounded-lg bg-zinc-900 border border-zinc-700 p-5">
      <h3 class="text-sm font-semibold text-zinc-100 mb-4">New Cloud Provider</h3>
      <form class="grid grid-cols-2 gap-4" @submit.prevent="addProvider">
        <label class="block col-span-2">
          <span class="text-xs text-zinc-500 block mb-1">Preset</span>
          <select
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none"
            @change="applyPreset(($event.target as HTMLSelectElement).value)"
          >
            <option value="">Custom provider…</option>
            <option v-for="p in presetOptions" :key="p.slug" :value="p.slug">
              {{ p.name }}
            </option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Name</span>
          <input v-model="newProvider.name" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Slug</span>
          <input v-model="newProvider.slug" class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none" placeholder="deepseek">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Base URL</span>
          <input v-model="newProvider.base_url" class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none" placeholder="https://...">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">API Key</span>
          <input v-model="newProvider.api_key" type="password" class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none" placeholder="sk-...">
        </label>
        <div class="col-span-2 flex gap-3">
          <button type="submit" class="px-4 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800">Save</button>
          <button type="button" class="px-4 py-1.5 text-sm rounded border border-zinc-700 text-zinc-400" @click="showAddForm = false">Cancel</button>
        </div>
      </form>
    </div>

    <p v-if="pending && providers.length === 0" class="text-sm text-zinc-500">Loading providers…</p>
    <p v-else-if="error" class="text-sm text-rose-400">Could not load providers.</p>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800">
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Name</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Type</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Health</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Active</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="providers.length === 0">
            <td colspan="5" class="px-4 py-6 text-center text-zinc-500 text-xs">No providers configured. Run db:seed or add one above.</td>
          </tr>
          <tr v-for="p in providers" :key="p.id" class="border-b border-zinc-800/50 hover:bg-zinc-800/20">
            <td class="px-4 py-3">
              <div class="text-sm font-medium text-zinc-100">{{ p.name }}</div>
              <div v-if="p.base_url" class="text-xs text-zinc-500 font-mono truncate max-w-xs">{{ p.base_url }}</div>
              <div v-if="p.api_key_masked" class="text-xs text-zinc-600">Key: {{ p.api_key_masked }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-zinc-400 font-mono">{{ p.type }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs border" :class="healthCls(p.health_status)">{{ p.health_status }}</span>
            </td>
            <td class="px-4 py-3">
              <button type="button" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors" :class="p.is_active ? 'bg-emerald-600' : 'bg-zinc-700'" @click="toggleActive(p)">
                <span class="inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform" :class="p.is_active ? 'translate-x-4.5' : 'translate-x-0.5'" />
              </button>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button type="button" class="px-2 py-1 text-xs rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800 disabled:opacity-50" :disabled="testingId === p.id || p.type === 'codex_oauth'" @click="testProvider(p.id)">
                  {{ testingId === p.id ? 'Testing...' : 'Test' }}
                </button>
                <button type="button" class="px-2 py-1 text-xs rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800 disabled:opacity-50" :disabled="syncingId === p.id || p.type === 'codex_oauth'" @click="syncModels(p.id)">
                  {{ syncingId === p.id ? 'Syncing...' : 'Sync Models' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
