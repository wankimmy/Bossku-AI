<script setup lang="ts">
import type { Plugin } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending, refresh } = await useAsyncData<Plugin[]>('plugins', () => api.get('/plugins'))

const plugins = computed<Plugin[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: Plugin[] }).data ?? []
})

const pingingId = ref<string | null>(null)
const toast = useToast()

async function ping(id: string) {
  pingingId.value = id
  try {
    await api.post(`/plugins/${id}/heartbeat`)
    toast.success('Heartbeat sent.')
    await refresh()
  }
  catch {
    toast.error('Failed to ping plugin.')
  }
  finally {
    pingingId.value = null
  }
}

async function togglePlugin(plugin: Plugin) {
  try {
    await api.patch(`/plugins/${plugin.id}`, { is_active: !plugin.is_active })
    toast.success(`Plugin ${plugin.is_active ? 'disabled' : 'enabled'}.`)
    await refresh()
  }
  catch {
    toast.error('Failed to update plugin.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-bold text-zinc-100">Plugins</h1>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-if="plugins.length === 0" class="col-span-full text-sm text-zinc-500 text-center py-8">
        No plugins installed.
      </div>
      <div
        v-for="plugin in plugins"
        :key="plugin.id"
        class="rounded-lg bg-zinc-900 border border-zinc-800 p-4 flex flex-col gap-3"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <h3 class="text-sm font-semibold text-zinc-100">{{ plugin.name }}</h3>
            <div class="flex items-center gap-2 mt-0.5">
              <span v-if="plugin.version" class="text-xs text-zinc-500">v{{ plugin.version }}</span>
              <span v-if="plugin.author" class="text-xs text-zinc-500">by {{ plugin.author }}</span>
            </div>
          </div>
          <button
            type="button"
            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0"
            :class="plugin.is_active ? 'bg-emerald-600' : 'bg-zinc-700'"
            @click="togglePlugin(plugin)"
          >
            <span
              class="inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform"
              :class="plugin.is_active ? 'translate-x-4.5' : 'translate-x-0.5'"
            />
          </button>
        </div>

        <p v-if="plugin.description" class="text-xs text-zinc-400">{{ plugin.description }}</p>

        <div v-if="plugin.last_heartbeat" class="text-xs text-zinc-600">
          Last seen: {{ new Date(plugin.last_heartbeat).toLocaleString() }}
        </div>

        <button
          type="button"
          class="mt-auto w-full py-1.5 text-xs rounded border border-zinc-700 text-zinc-400 hover:bg-zinc-800 disabled:opacity-50"
          :disabled="pingingId === plugin.id"
          @click="ping(plugin.id)"
        >
          {{ pingingId === plugin.id ? 'Pinging...' : 'Ping heartbeat' }}
        </button>
      </div>
    </div>
  </div>
</template>
