<script setup lang="ts">
definePageMeta({ layout: 'default' })

const prompt = ref('')
const { events, running, error, start, stop } = useRunStream()
const tab = ref<'chat' | 'timeline' | 'context'>('chat')

const finalOutput = computed(() => {
  const done = [...events.value].reverse().find(e => String(e?.type) === 'run_completed')
  if (done?.output !== undefined && done.output !== null) {
    return String(done.output)
  }
  return ''
})

async function syncRun() {
  const base = useApiBase()
  try {
    const res = await $fetch<{ final_output?: string }>(`${base}/api/runs`, {
      method: 'POST',
      body: { prompt: prompt.value },
    })

    alert(res.final_output || 'Completed')
  }
  catch (e: unknown) {
    alert(`Run failed: ${e instanceof Error ? e.message : String(e)}`)
  }
}

function submit() {
  if (!prompt.value.trim()) return
  start(prompt.value.trim())
}
</script>

<template>
  <div class="flex flex-col gap-4 pb-28 md:pb-6">
    <div class="md:grid md:grid-cols-12 md:gap-6">
      <!-- Desktop / tablet left + center -->
      <section class="space-y-4 md:col-span-12 lg:col-span-8">
        <!-- Mobile tabs -->
        <div class="flex gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900 md:hidden">
          <button
            v-for="x in [{ id:'chat',l:'Chat'},{id:'timeline',l:'Timeline'},{id:'context',l:'Context'}] as const"
            :key="x.id"
            type="button"
            class="flex-1 rounded-md px-2 py-2 text-sm font-medium"
            :class="tab === x.id ? 'bg-white text-zinc-900 shadow dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400'"
            @click="tab = x.id"
          >
            {{ x.l }}
          </button>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" :class="tab !== 'chat' ? 'hidden md:block' : ''">
          <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <h1 class="text-lg font-semibold">
              Run task
            </h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              Streams memory retrieval → skill router → planner → executor (Ollama) → auditor.
            </p>
          </div>
          <div class="space-y-3 p-4">
            <textarea
              v-model="prompt"
              class="block min-h-[100px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
              placeholder="Describe what you want done…"
            />
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                :disabled="running || !prompt.trim()"
                @click="submit"
              >
                Stream run
              </button>
              <button
                type="button"
                class="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                :disabled="running || !prompt.trim()"
                @click="syncRun"
              >
                Run (sync API)
              </button>
              <button
                v-if="running"
                type="button"
                class="rounded-lg border border-rose-300 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:text-rose-300"
                @click="stop"
              >
                Stop stream
              </button>
            </div>
            <p v-if="error" class="text-sm text-rose-700 dark:text-rose-400">
              {{ error }}
            </p>
          </div>
          <div v-if="finalOutput" class="border-t border-zinc-100 p-4 dark:border-zinc-800">
            <h2 class="text-sm font-semibold">
              Final answer
            </h2>
            <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed">
              {{ finalOutput }}
            </div>
          </div>
        </div>

        <div :class="tab !== 'timeline' ? 'hidden md:block' : ''">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2 class="text-sm font-semibold">
              Execution timeline
            </h2>
            <span v-if="running" class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">Streaming…</span>


          </div>
          <ExecutionTimeline :events="events as Record<string, unknown>[]" />
        </div>
      </section>

      <aside class="hidden space-y-4 lg:col-span-4 lg:block">
        <ContextDrawer title="Active context panel" :context-events="events as Record<string, unknown>[]" />
      </aside>
    </div>

    <section :class="tab !== 'context' ? 'hidden md:block lg:hidden' : 'md:hidden'">
      <ContextDrawer title="Context" :context-events="events as Record<string, unknown>[]" />
    </section>

    <!-- Sticky prompt mobile -->
    <div class="fixed inset-x-0 bottom-14 z-30 border-t border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950 md:hidden">
      <div class="mx-auto flex max-w-[1600px] gap-2">
        <button
          type="button"
          class="flex-1 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-40"
          :disabled="running || !prompt.trim()"
          @click="submit"
        >
          Run task
        </button>
      </div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-30 flex gap-px border-t border-zinc-200 bg-zinc-50 text-[11px] font-medium md:hidden dark:border-zinc-800 dark:bg-zinc-950">
      <NuxtLink to="/skills" class="flex-1 border-r border-zinc-200 px-1 py-2 text-center dark:border-zinc-800">
        More
      </NuxtLink>
      <button type="button" class="flex-1 px-1 py-2 text-center text-emerald-800 dark:text-emerald-400" @click="tab='chat'">
        Chat
      </button>
      <button type="button" class="flex-1 border-l border-zinc-200 px-1 py-2 text-center dark:border-zinc-800" @click="tab='timeline'">
        Timeline
      </button>
      <button type="button" class="flex-1 border-l border-zinc-200 px-1 py-2 text-center dark:border-zinc-800" @click="tab='context'">
        Context
      </button>
      <NuxtLink to="/memory" class="flex-1 border-l border-zinc-200 px-1 py-2 text-center dark:border-zinc-800">
        Memory
      </NuxtLink>
    </nav>
  </div>
</template>
