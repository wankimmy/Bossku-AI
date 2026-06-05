<script setup lang="ts">
const api = useApi()
const router = useRouter()

const parentPrompt = ref('')
const childPrompts = ref(['', ''])
const busy = ref(false)
const error = ref('')

async function spawn() {
  const tasks = childPrompts.value.map(p => p.trim()).filter(Boolean).map(prompt => ({ prompt }))
  if (!parentPrompt.value.trim() || tasks.length === 0) {
    error.value = 'Parent prompt and at least one child task are required.'
    return
  }
  busy.value = true
  error.value = ''
  try {
    const res = await api.post('/runs/supervisor/spawn', {
      prompt: parentPrompt.value.trim(),
      tasks,
    }) as Record<string, unknown>
    const parentId = res.parent_run_id
    if (parentId) await router.push(`/runs/${parentId}`)
  }
  catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Spawn failed.'
  }
  finally {
    busy.value = false
  }
}

function addChild() {
  if (childPrompts.value.length < 4) childPrompts.value.push('')
}
</script>

<template>
  <section class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 space-y-3">
    <h3 class="text-sm font-semibold text-zinc-200">
      Spawn parallel fleet
    </h3>
    <textarea
      v-model="parentPrompt"
      rows="2"
      placeholder="Parent supervisor goal..."
      class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200"
    />
    <div v-for="(task, i) in childPrompts" :key="i" class="space-y-1">
      <label class="text-xs text-zinc-500">Child {{ i + 1 }}</label>
      <textarea
        v-model="childPrompts[i]"
        rows="2"
        :placeholder="`Child task ${i + 1}...`"
        class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200"
      />
    </div>
    <div class="flex gap-2">
      <button type="button" class="text-xs text-sky-400 hover:underline" @click="addChild">
        + Add child
      </button>
      <button
        type="button"
        class="ml-auto rounded bg-emerald-700 px-3 py-1.5 text-xs text-white hover:bg-emerald-600 disabled:opacity-50"
        :disabled="busy"
        @click="spawn"
      >
        Spawn fleet
      </button>
    </div>
    <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
  </section>
</template>
