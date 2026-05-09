<script setup lang="ts">
definePageMeta({ layout: 'default' })

const prompt = ref('')
const { events, running, error, start, stop } = useRunStream()
const mobileTab = ref<'chat' | 'plan' | 'changes' | 'audit' | 'memory'>('chat')

const artifacts = computed(() => useRunArtifacts(events.value as Record<string, unknown>[]))
const status = computed(() => {
  const last = events.value.at(-1)
  return last ? String(last.status ?? last.type ?? 'running') : 'idle'
})

async function syncRun() {
  const base = useApiBase()
  try {
    const res = await $fetch<{ final_output?: string }>(`${base}/api/runs`, {
      method: 'POST',
      body: { prompt: prompt.value },
    })
    events.value.push({
      type: 'run_completed',
      agent: 'final-reviewer',
      status: 'success',
      output: res.final_output || 'Completed',
    })
  }
  catch (e: unknown) {
    events.value.push({
      type: 'run_failed',
      agent: 'system',
      status: 'fail',
      summary: 'Run failed.',
      message: e instanceof Error ? e.message : String(e),
    })
  }
}

function submit() {
  if (!prompt.value.trim()) return
  mobileTab.value = 'chat'
  start(prompt.value.trim())
}

const navLinks = [
  { to: '/runs', label: 'Runs' },
  { to: '/skills', label: 'Skills' },
  { to: '/memory', label: 'Memory' },
  { to: '/settings', label: 'Settings' },
]
</script>

<template>
  <div class="space-y-4 pb-28 md:pb-6">
    <RunStatusHeader
      :running="running"
      :status="status"
      :memory-used="artifacts.memoryUsed"
      :routing="artifacts.routingSummary"
    />

    <div class="flex gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900 md:hidden">
      <button
        v-for="tab in ['chat', 'plan', 'changes', 'audit', 'memory'] as const"
        :key="tab"
        type="button"
        class="flex-1 rounded-md px-2 py-2 text-xs font-medium capitalize"
        :class="mobileTab === tab ? 'bg-white text-zinc-900 shadow dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400'"
        @click="mobileTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <div class="grid gap-4 lg:grid-cols-[190px_minmax(0,1fr)_380px]">
      <aside class="hidden space-y-3 lg:block">
        <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
          <h2 class="text-xs font-semibold uppercase text-zinc-500">
            Workspace
          </h2>
          <nav class="mt-3 space-y-1 text-sm" aria-label="Workspace navigation">
            <NuxtLink
              v-for="link in navLinks"
              :key="link.to"
              :to="link.to"
              class="block rounded-md px-2 py-1.5 text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
            >
              {{ link.label }}
            </NuxtLink>
          </nav>
        </section>
        <AgentHandoffFlow :nodes="artifacts.handoffNodes" />
      </aside>

      <main :class="mobileTab !== 'chat' ? 'hidden md:block' : ''" class="space-y-4">
        <AgentHandoffFlow class="lg:hidden" :nodes="artifacts.handoffNodes" />
        <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
          <label for="run-prompt" class="text-sm font-semibold">Task prompt</label>
          <textarea
            id="run-prompt"
            v-model="prompt"
            class="mt-2 block min-h-[110px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
            placeholder="Describe the engineering task..."
          />
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-700"
              :disabled="running || !prompt.trim()"
              @click="submit"
            >
              Run task
            </button>
            <button
              type="button"
              class="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
              :disabled="running || !prompt.trim()"
              @click="syncRun"
            >
              Run sync API
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
          <p v-if="error" class="mt-3 text-sm text-rose-700 dark:text-rose-400">
            {{ error }}
          </p>
        </section>

        <section class="space-y-3" aria-live="polite">
          <h2 class="text-sm font-semibold">
            Agent conversation
          </h2>
          <AgentMessageCard
            v-for="message in artifacts.agentMessages"
            :key="message.id"
            :message="message"
          />
          <UiEmptyState
            v-if="artifacts.agentMessages.length === 0"
            title="No agent messages yet."
            hint="Run a task to see orchestrator, executor, auditor, and final reviewer updates."
          />
        </section>

        <FinalResultPanel v-if="artifacts.finalResult.raw" :result="artifacts.finalResult" />

        <details class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
          <summary class="cursor-pointer text-sm font-semibold">
            Raw JSON
          </summary>
          <ExecutionTimeline class="mt-3" :events="events as Record<string, unknown>[]" />
        </details>
      </main>

      <aside class="space-y-4">
        <div :class="mobileTab !== 'plan' ? 'hidden md:block' : ''">
          <PlanChecklist :items="artifacts.checklist" />
        </div>
        <div :class="mobileTab !== 'changes' ? 'hidden md:block' : ''">
          <ChangeTrackerPanel
            :files-read="artifacts.filesRead"
            :files-changed="artifacts.filesChanged"
            :commands-run="artifacts.commandsRun"
            :tests-run="artifacts.testsRun"
          />
        </div>
        <div :class="mobileTab !== 'audit' ? 'hidden md:block' : ''">
          <AuditFindingsPanel
            :status="artifacts.finalResult.auditResult"
            :findings="artifacts.auditFindings"
          />
        </div>
        <div :class="mobileTab !== 'memory' ? 'hidden md:block' : ''">
          <ContextDrawer title="Context used" :context-events="events as Record<string, unknown>[]" />
        </div>
      </aside>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950 md:hidden">
      <button
        type="button"
        class="w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-40"
        :disabled="running || !prompt.trim()"
        @click="submit"
      >
        Run task
      </button>
    </div>
  </div>
</template>
