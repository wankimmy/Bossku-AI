<script setup lang="ts">
const props = defineProps<{
  run: Record<string, unknown>
}>()

const api = useApi()
const owner = ref('')
const repo = ref('')
const pullNumber = ref<number | ''>('')
const busy = ref(false)
const message = ref('')

const scm = computed(() => {
  const meta = props.run?.metadata
  if (meta && typeof meta === 'object') {
    const s = (meta as Record<string, unknown>).scm
    return s && typeof s === 'object' ? s as Record<string, unknown> : null
  }
  return null
})

const reactions = computed(() => {
  const list = props.run?.reaction_states ?? props.run?.reactionStates
  return Array.isArray(list) ? list as Record<string, unknown>[] : []
})

onMounted(() => {
  if (scm.value) {
    owner.value = String(scm.value.owner ?? '')
    repo.value = String(scm.value.repo ?? '')
    pullNumber.value = Number(scm.value.pull_number ?? '') || ''
  }
})

const emit = defineEmits<{ refresh: [] }>()

async function attachScm() {
  if (!owner.value || !repo.value || !pullNumber.value) return
  busy.value = true
  message.value = ''
  try {
    await api.post(`/runs/${props.run.id}/scm`, {
      owner: owner.value,
      repo: repo.value,
      pull_number: Number(pullNumber.value),
      provider: 'github',
    })
    message.value = 'PR linked. Scheduler will poll CI/review reactions.'
    emit('refresh')
  }
  catch (e: unknown) {
    message.value = e instanceof Error ? e.message : 'Failed to attach PR.'
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 space-y-3">
    <h3 class="text-sm font-semibold text-zinc-200">
      SCM reactions
    </h3>
    <p class="text-xs text-zinc-500">
      Link a GitHub PR to auto-resume this run on CI failure or review comments (requires <code class="text-zinc-400">BOSSKU_GITHUB_TOKEN</code> and queue worker).
    </p>

    <div v-if="scm" class="text-xs text-emerald-400 font-mono">
      Linked: {{ scm.owner }}/{{ scm.repo }}#{{ scm.pull_number }}
    </div>

    <div class="grid gap-2 sm:grid-cols-3 text-xs">
      <input v-model="owner" placeholder="owner" class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-zinc-200">
      <input v-model="repo" placeholder="repo" class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-zinc-200">
      <input v-model="pullNumber" type="number" min="1" placeholder="PR #" class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-zinc-200">
    </div>
    <button
      type="button"
      class="rounded bg-sky-700 px-3 py-1.5 text-xs text-white hover:bg-sky-600 disabled:opacity-50"
      :disabled="busy"
      @click="attachScm"
    >
      {{ scm ? 'Update PR link' : 'Attach PR' }}
    </button>
    <p v-if="message" class="text-xs text-zinc-400">{{ message }}</p>

    <ul v-if="reactions.length" class="space-y-2 text-xs">
      <li v-for="r in reactions" :key="String(r.id)" class="rounded border border-zinc-800 p-2">
        <div class="flex justify-between text-zinc-300">
          <span>{{ r.reaction_key }}</span>
          <span>attempts: {{ r.attempts }}</span>
        </div>
        <p v-if="r.last_triggered_at" class="text-zinc-600 mt-1">last: {{ r.last_triggered_at }}</p>
      </li>
    </ul>
  </section>
</template>
