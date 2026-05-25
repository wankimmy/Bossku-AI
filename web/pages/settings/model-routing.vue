<script setup lang="ts">
import type { InferenceModelOption } from '~/composables/useInferenceCatalog'
import type { LlmProvider, ModelRoute } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data: providersData, pending: providersPending, error: providersError } = useProviders()
const { optgroups: inferenceGroups } = useInferenceCatalog()
const { data, pending, error, refresh } = await useAsyncData<ModelRoute[]>(
  'model-routes',
  () => api.get('/model-routes'),
  { server: false, lazy: true },
)

const routes = computed<ModelRoute[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: ModelRoute[] }).data ?? []
})

const providers = computed<LlmProvider[]>(() => {
  const d = providersData.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: LlmProvider[] }).data ?? []
})

const showAddForm = ref(false)
const newRoute = reactive({
  role: '',
  primary_provider_id: '',
  primary_model: '',
  fallback_provider_id: '',
  fallback_model: '',
})
const toast = useToast()

const catalogOptions = computed(() =>
  inferenceGroups.value.flatMap(group => group.options),
)

function catalogLabel(modelId: string): string {
  return catalogOptions.value.find(option => option.id === modelId)?.label ?? modelId
}

function modelOptionsForProvider(providerId: string): InferenceModelOption[] {
  const provider = providers.value.find(item => item.id === providerId)
  if (!provider) return []

  const availableModels = Array.isArray(provider.available_models)
    ? provider.available_models.filter((model): model is string => typeof model === 'string' && model.trim() !== '')
    : []

  if (availableModels.length) {
    return availableModels.map(model => ({
      id: model,
      label: catalogLabel(model),
    }))
  }

  const providerGroup = inferenceGroups.value.find(group => group.provider === provider.type)
  if (providerGroup?.options.length) {
    return providerGroup.options
  }

  return catalogOptions.value
}

const primaryModelOptions = computed(() => modelOptionsForProvider(newRoute.primary_provider_id))
const fallbackModelOptions = computed(() => modelOptionsForProvider(newRoute.fallback_provider_id))

watch(primaryModelOptions, (options) => {
  if (options.length === 0) {
    newRoute.primary_model = ''
    return
  }
  if (!options.some(option => option.id === newRoute.primary_model)) {
    newRoute.primary_model = options[0]?.id ?? ''
  }
}, { immediate: true })

watch(fallbackModelOptions, (options) => {
  if (options.length === 0) {
    newRoute.fallback_model = ''
    return
  }
  if (!options.some(option => option.id === newRoute.fallback_model)) {
    newRoute.fallback_model = options[0]?.id ?? ''
  }
}, { immediate: true })

async function addRoute() {
  try {
    await api.post('/model-routes', {
      role: newRoute.role.trim(),
      primary_provider_id: newRoute.primary_provider_id || null,
      primary_model: newRoute.primary_model.trim(),
      fallback_provider_id: newRoute.fallback_provider_id || null,
      fallback_model: newRoute.fallback_model.trim() || null,
      is_active: true,
    })
    toast.success(`Route for "${newRoute.role}" added.`)
    showAddForm.value = false
    Object.assign(newRoute, {
      role: '',
      primary_provider_id: '',
      primary_model: '',
      fallback_provider_id: '',
      fallback_model: '',
    })
    await refresh()
  }
  catch {
    toast.error('Failed to add route.')
  }
}

async function deleteRoute(id: string) {
  try {
    await api.del(`/model-routes/${id}`)
    toast.success('Route deleted.')
    await refresh()
  }
  catch {
    toast.error('Failed to delete route.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-zinc-100">Model Routing</h1>
        <p class="text-sm text-zinc-500 mt-1">Configure which model handles each agent role.</p>
      </div>
      <button
        type="button"
        class="px-3 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50"
        @click="showAddForm = !showAddForm"
      >
        + Add Route
      </button>
    </div>

    <div v-if="showAddForm" class="rounded-lg bg-zinc-900 border border-zinc-700 p-5">
      <h3 class="text-sm font-semibold text-zinc-100 mb-4">New Route</h3>
      <form class="grid grid-cols-2 gap-4" @submit.prevent="addRoute">
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Role</span>
          <input v-model="newRoute.role" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Primary Provider</span>
          <select v-model="newRoute.primary_provider_id" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
            <option value="" disabled>Select provider</option>
            <option v-for="provider in providers" :key="provider.id" :value="provider.id">
              {{ provider.name }} ({{ provider.type }})
            </option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Primary Model</span>
          <select v-model="newRoute.primary_model" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
            <option value="" disabled>Select model</option>
            <option v-for="m in primaryModelOptions" :key="m.id" :value="m.id">
              {{ m.label }}
            </option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Fallback Provider</span>
          <select v-model="newRoute.fallback_provider_id" class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
            <option value="">No fallback provider</option>
            <option v-for="provider in providers" :key="provider.id" :value="provider.id">
              {{ provider.name }} ({{ provider.type }})
            </option>
          </select>
          <span class="mt-1 block text-xs text-zinc-600">
            Select a fallback provider before choosing a fallback model.
          </span>
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Fallback Model</span>
          <select
            v-model="newRoute.fallback_model"
            :disabled="!newRoute.fallback_provider_id || fallbackModelOptions.length === 0"
            class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none disabled:cursor-not-allowed disabled:opacity-60"
          >
            <option value="">No fallback model</option>
            <option v-for="m in fallbackModelOptions" :key="m.id" :value="m.id">
              {{ m.label }}
            </option>
          </select>
        </label>
        <div class="col-span-2 flex gap-3">
          <button type="submit" class="px-4 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800">Save</button>
          <button type="button" class="px-4 py-1.5 text-sm rounded border border-zinc-700 text-zinc-400" @click="showAddForm = false">Cancel</button>
        </div>
      </form>
    </div>

    <div v-if="pending || providersPending" class="text-sm text-zinc-500">Loading...</div>
    <div v-else-if="providersError" class="rounded-lg border border-rose-500/30 bg-rose-950/20 px-4 py-3 text-sm text-rose-200">
      Could not load providers. Check that the API is running.
    </div>
    <div v-else-if="providers.length === 0" class="rounded-lg border border-amber-500/30 bg-amber-950/20 px-4 py-3 text-sm text-amber-200">
      No providers available. Add or sync providers first so route model dropdowns can populate.
    </div>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800">
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Role</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Primary Provider / Model</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Fallback Provider / Model</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="routes.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-zinc-500 text-xs">No model routes configured.</td>
          </tr>
          <tr v-for="r in routes" :key="r.id" class="border-b border-zinc-800/50 hover:bg-zinc-800/20">
            <td class="px-4 py-3 text-xs font-mono text-zinc-300">{{ r.role }}</td>
            <td class="px-4 py-3 text-xs text-zinc-300">
              <div class="font-medium">{{ r.primary_provider_name ?? '—' }}</div>
              <div class="font-mono text-zinc-500">{{ r.primary_model }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-zinc-500">
              <div class="font-medium text-zinc-300">{{ r.fallback_provider_name ?? '—' }}</div>
              <div class="font-mono">{{ r.fallback_model ?? '—' }}</div>
            </td>
            <td class="px-4 py-3">
              <button
                type="button"
                class="text-xs text-red-400 hover:text-red-300"
                @click="deleteRoute(r.id)"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
