<script setup lang="ts">
definePageMeta({ layout: 'default' })

const api = useApi()
const providers = ref<Record<string, unknown>>({})
const remote = ref<Record<string, unknown>>({})
const loading = ref(true)

onMounted(async () => {
  try {
    providers.value = await api.get('/providers/cli') as Record<string, unknown>
    remote.value = await api.get('/remote-execution/status') as Record<string, unknown>
  }
  catch {
    /* optional endpoints */
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6 p-6">
    <h1 class="text-2xl font-semibold text-zinc-100">
      Orchestrator
    </h1>
    <p class="text-sm text-zinc-400">
      Worktrees, parallel supervisor fleets, provider CLIs, and SCM reactions.
    </p>

    <UiSkeleton v-if="loading" class="h-32 w-full" />

    <section v-else class="space-y-4">
      <SupervisorSpawnForm />

      <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4">
        <h2 class="text-sm font-medium text-zinc-200">
          Installed provider CLIs
        </h2>
        <p class="mt-1 text-xs text-zinc-500">
          On desktop, CLIs are detected from your Windows PATH (not inside the PHP container).
        </p>
        <ul class="mt-2 space-y-1 text-xs text-zinc-400">
          <li v-for="p in (providers.installed as unknown[] || [])" :key="String((p as Record<string, unknown>).id)">
            {{ (p as Record<string, unknown>).display_name }} — {{ (p as Record<string, unknown>).version || 'detected' }}
          </li>
          <li v-if="!(providers.installed as unknown[] || []).length">
            No provider CLIs detected. Install Claude Code, Codex, or Cursor CLI on PATH.
          </li>
        </ul>
      </div>

      <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 text-xs text-zinc-400 space-y-2">
        <p><strong class="text-zinc-300">Desktop / native runtime</strong> starts API + queue worker + scheduler automatically.</p>
        <p>SSH execution: {{ remote.enabled ? 'enabled' : 'disabled' }}</p>
        <p>BYOI workspaces: {{ remote.byoi_enabled ? 'enabled' : 'disabled' }}</p>
        <p class="text-zinc-500">
          Key env vars: <code class="text-zinc-300">BOSSKU_WORKTREE_ENABLED</code>,
          <code class="text-zinc-300">BOSSKU_GITHUB_TOKEN</code>,
          <code class="text-zinc-300">BOSSKU_AGENT_HOOK_TOKEN</code>,
          <code class="text-zinc-300">QUEUE_CONNECTION=database</code> (desktop).
        </p>
      </div>
    </section>
  </div>
</template>
