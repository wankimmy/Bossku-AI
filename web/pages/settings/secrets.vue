<script setup lang="ts">
import type { LlmProvider } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data: providersData } = await useAsyncData('providers-secrets', () => api.get('/providers'), { server: false, lazy: true })
const { data: settingsData } = await useFetch<Record<string, string>>(apiUrl('/settings'), { server: false, lazy: true })

const codexStatus = ref<{ connected: boolean, account_hint?: string | null } | null>(null)

onMounted(async () => {
  try {
    codexStatus.value = await $fetch(apiUrl('/oauth/codex/status'))
  }
  catch {
    codexStatus.value = { connected: false }
  }
})

const providers = computed<LlmProvider[]>(() => {
  const d = providersData.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: LlmProvider[] }).data ?? []
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-zinc-100">Secrets</h1>
      <p class="text-sm text-zinc-500 mt-1">Masked overview of cloud provider credentials.</p>
    </div>

    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4 space-y-3">
      <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Runtime settings</h2>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <span class="text-zinc-500">Ollama API key</span>
          <p class="font-mono text-zinc-300">{{ settingsData?.ollama_api_key_masked ?? 'Not set' }}</p>
        </div>
        <div>
          <span class="text-zinc-500">Anthropic API key</span>
          <p class="font-mono text-zinc-300">{{ settingsData?.anthropic_api_key_masked ?? 'Not set' }}</p>
        </div>
        <div>
          <span class="text-zinc-500">Codex OAuth</span>
          <p class="text-zinc-300">
            {{ codexStatus?.connected ? `Connected${codexStatus.account_hint ? ` (${codexStatus.account_hint})` : ''}` : 'Not connected' }}
          </p>
        </div>
      </div>
      <NuxtLink to="/settings/models" class="text-sm text-emerald-400 hover:underline">Edit Ollama / Anthropic / Codex → Models</NuxtLink>
    </div>

    <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-zinc-800">
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Provider</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Auth</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Key</th>
            <th class="px-4 py-3 text-left text-xs text-zinc-500">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in providers" :key="p.id" class="border-b border-zinc-800/50">
            <td class="px-4 py-3 text-zinc-200">{{ p.name }}</td>
            <td class="px-4 py-3 text-xs text-zinc-500">{{ p.type === 'codex_oauth' ? 'OAuth' : 'API key' }}</td>
            <td class="px-4 py-3 font-mono text-xs text-zinc-400">{{ p.api_key_masked ?? (p.type === 'codex_oauth' ? 'OAuth' : '—') }}</td>
            <td class="px-4 py-3 text-xs" :class="p.is_active ? 'text-emerald-400' : 'text-zinc-500'">{{ p.is_active ? 'Active' : 'Inactive' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <NuxtLink to="/settings/providers" class="inline-block text-sm text-emerald-400 hover:underline">Manage provider keys → Providers</NuxtLink>
  </div>
</template>
