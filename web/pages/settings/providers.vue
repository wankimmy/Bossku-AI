<script setup lang="ts">
import type { LlmProvider } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending, refresh } = await useAsyncData<LlmProvider[]>('providers', () => api.get('/providers'))

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

const testingId = ref<string | null>(null)
const syncingId = ref<string | null>(null)

async function testProvider(id: string) {
  testingId.value = id
  try {
    await api.post(`/providers/${id}/test`)
  }
  finally {
    testingId.value = null
    await refresh()
  }
}

async function syncModels(id: string) {
  syncingId.value = id
  try {
    await api.post(`/providers/${id}/sync-models`)
  }
  finally {
    syncingId.value = null
    await refresh()
  }
}

async function toggleActive(provider: LlmProvider) {
  await api.patch(`/providers/${provider.id}`, { is_active: !provider.is_active })
  await refresh()
}

// Add new provider form
const showAddForm = ref(false)
const newProvider = reactive({ name: '', type: 'openai', base_url: '', api_key: '' })

async function addProvider() {
  await api.post('/providers', { ...newProvider })
  showAddForm.value = false
  Object.assign(newProvider, { name: '', type: 'openai', base_url: '', api_key: '' })
  await refresh()
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-zinc-100">LLM Providers</h1>
        <p class="text-sm text-zinc-500 mt-1">Manage AI model providers and their configurations.</p>
      </div>
      <button
        type="button"
        class="px-3 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50"
        @click="showAddForm = !showAddForm"
      >
        + Add Provider
      </button>
    </div>

    <!-- Add form -->
    <div v-if="showAddForm" class="rounded-lg bg-zinc-900 border border-zinc-700 p-5">
      <h3 class="text-sm font-semibold text-zinc-100 mb-4">New Provider</h3>
      <form class="grid grid-cols-2 gap-4" @submit.prevent="addProvider">
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Name</span>
          <input
            v-model="newProvider.name"
            required
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500"
          >
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Type</span>
          <select
            v-model="newProvider.type"
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none"
          >
            <option value="openai">OpenAI</option>
            <option value="anthropic">Anthropic</option>
            <option value="ollama">Ollama</option>
            <option value="openrouter">OpenRouter</option>
            <option value="custom">Custom</option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Base URL</span>
          <input
            v-model="newProvider.base_url"
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500"
            placeholder="https://..."
          >
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">API Key</span>
          <input
            v-model="newProvider.api_key"
            type="password"
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500"
            placeholder="sk-..."
          >
        </label>
        <div class="col-span-2 flex gap-3">
          <button type="submit" class="px-4 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50">
            Save
          </button>
          <button type="button" class="px-4 py-1.5 text-sm rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800" @click="showAddForm = false">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <div class="overflow-x-auto">
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
              <td colspan="5" class="px-4 py-6 text-center text-zinc-500 text-xs">No providers configured.</td>
            </tr>
            <tr
              v-for="p in providers"
              :key="p.id"
              class="border-b border-zinc-800/50 hover:bg-zinc-800/20"
            >
              <td class="px-4 py-3">
                <div class="text-sm font-medium text-zinc-100">{{ p.name }}</div>
                <div v-if="p.base_url" class="text-xs text-zinc-500 font-mono truncate max-w-xs">{{ p.base_url }}</div>
              </td>
              <td class="px-4 py-3 text-xs text-zinc-400 font-mono">{{ p.type }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs border" :class="healthCls(p.health_status)">
                  {{ p.health_status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                  :class="p.is_active ? 'bg-emerald-600' : 'bg-zinc-700'"
                  @click="toggleActive(p)"
                >
                  <span
                    class="inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform"
                    :class="p.is_active ? 'translate-x-4.5' : 'translate-x-0.5'"
                  />
                </button>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="testingId === p.id"
                    @click="testProvider(p.id)"
                  >
                    {{ testingId === p.id ? 'Testing...' : 'Test' }}
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="syncingId === p.id"
                    @click="syncModels(p.id)"
                  >
                    {{ syncingId === p.id ? 'Syncing...' : 'Sync Models' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
