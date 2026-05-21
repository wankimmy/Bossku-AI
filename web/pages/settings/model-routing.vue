<script setup lang="ts">
import type { ModelRoute } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending, refresh } = await useAsyncData<ModelRoute[]>('model-routes', () => api.get('/model-routes'))

const routes = computed<ModelRoute[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: ModelRoute[] }).data ?? []
})

const showAddForm = ref(false)
const newRoute = reactive({ role: '', primary_model: '', fallback_model: '' })

async function addRoute() {
  await api.post('/model-routes', { ...newRoute })
  showAddForm.value = false
  Object.assign(newRoute, { role: '', primary_model: '', fallback_model: '' })
  await refresh()
}

async function deleteRoute(id: string) {
  await api.del(`/model-routes/${id}`)
  await refresh()
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
      <form class="grid grid-cols-3 gap-4" @submit.prevent="addRoute">
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Role</span>
          <input v-model="newRoute.role" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Primary Model</span>
          <input v-model="newRoute.primary_model" required class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
        </label>
        <label class="block">
          <span class="text-xs text-zinc-500 block mb-1">Fallback Model</span>
          <input v-model="newRoute.fallback_model" class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none">
        </label>
        <div class="col-span-3 flex gap-3">
          <button type="submit" class="px-4 py-1.5 text-sm rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800">Save</button>
          <button type="button" class="px-4 py-1.5 text-sm rounded border border-zinc-700 text-zinc-400" @click="showAddForm = false">Cancel</button>
        </div>
      </form>
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800">
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Role</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Primary Model</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Fallback Model</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="routes.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-zinc-500 text-xs">No model routes configured.</td>
          </tr>
          <tr v-for="r in routes" :key="r.id" class="border-b border-zinc-800/50 hover:bg-zinc-800/20">
            <td class="px-4 py-3 text-xs font-mono text-zinc-300">{{ r.role }}</td>
            <td class="px-4 py-3 text-xs font-mono text-zinc-300">{{ r.primary_model }}</td>
            <td class="px-4 py-3 text-xs font-mono text-zinc-500">{{ r.fallback_model ?? '—' }}</td>
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
