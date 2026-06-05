<script setup lang="ts">
const props = defineProps<{
  run: Record<string, unknown>
}>()

const api = useApi()
const providers = ref<Record<string, unknown>>({})
const busy = ref(false)
const message = ref('')

const sessions = computed(() => {
  const list = props.run?.cli_sessions ?? props.run?.cliSessions
  return Array.isArray(list) ? list as Record<string, unknown>[] : []
})

onMounted(async () => {
  try {
    providers.value = await api.get('/providers/cli') as Record<string, unknown>
  }
  catch { /* optional */ }
})

async function startSession(providerId: string) {
  busy.value = true
  message.value = ''
  try {
    const runId = String(props.run.id)
    await api.post(`/runs/${runId}/cli-session`, {
      provider: providerId,
      async: true,
    })
    message.value = 'CLI session started.'
    await refreshRun()
  }
  catch (e: unknown) {
    message.value = e instanceof Error ? e.message : 'Failed to start CLI session.'
  }
  finally {
    busy.value = false
  }
}

const emit = defineEmits<{ refresh: [] }>()

async function refreshRun() {
  emit('refresh')
}
</script>

<template>
  <section class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 space-y-3">
    <h3 class="text-sm font-semibold text-zinc-200">
      Provider CLI sessions
    </h3>
    <p class="text-xs text-zinc-500">
      Launch host-installed CLIs in this run&apos;s worktree (desktop detects CLIs on Windows PATH).
    </p>

    <div v-if="(providers.installed as unknown[] || []).length" class="flex flex-wrap gap-2">
      <button
        v-for="p in (providers.installed as Record<string, unknown>[])"
        :key="String(p.id)"
        type="button"
        class="rounded bg-zinc-800 px-3 py-1.5 text-xs text-zinc-200 hover:bg-zinc-700 disabled:opacity-50"
        :disabled="busy"
        @click="startSession(String(p.id))"
      >
        Start {{ p.display_name }}
      </button>
    </div>
    <p v-else class="text-xs text-amber-500">
      No provider CLIs detected. Install Claude, Codex, or Cursor CLI on your PATH.
    </p>

    <p v-if="message" class="text-xs text-zinc-400">{{ message }}</p>

    <ul v-if="sessions.length" class="space-y-2 text-xs">
      <li v-for="s in sessions" :key="String(s.id)" class="rounded border border-zinc-800 p-2">
        <div class="flex justify-between">
          <span class="text-zinc-300">{{ s.provider }} — {{ String(s.id).slice(0, 8) }}</span>
          <span class="uppercase text-zinc-500">{{ s.status }}</span>
        </div>
        <p v-if="s.metadata && typeof s.metadata === 'object'" class="text-zinc-600 font-mono mt-1 truncate">
          cwd: {{ (s.metadata as Record<string, unknown>).cwd }}
        </p>
      </li>
    </ul>
  </section>
</template>
