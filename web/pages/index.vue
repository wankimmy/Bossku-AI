<script setup lang="ts">
definePageMeta({ layout: 'default' })

const prompt = ref('')
const { events, running, error, start, stop } = useRunStream()
const toast = useToast()
const mobileTab = ref<'chat' | 'plan' | 'changes' | 'audit' | 'memory' | 'agents'>('agents')

const artifacts = computed(() => useRunArtifacts(events.value as Record<string, unknown>[]))
const status = computed(() => {
  const last = events.value.at(-1)
  return last ? String(last.status ?? last.type ?? 'running') : 'idle'
})

// Final AI response and error surfacing
const finalOutput = computed(() => {
  const done = events.value.findLast((e: Record<string, unknown>) => e.type === 'run_completed')
  return done ? String(done.output ?? done.final_output ?? '') : ''
})
const runError = computed(() => {
  if (error.value) return String(error.value)
  const failed = events.value.findLast((e: Record<string, unknown>) => e.type === 'run_failed')
  if (!failed) return ''
  const plannerFailed = events.value.findLast((e: Record<string, unknown>) => e.type === 'planner_failed')
  return String(
    failed.error ?? failed.message ?? plannerFailed?.error ?? plannerFailed?.message ?? failed.summary ?? 'Run failed.',
  )
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
  toast.info('Task started…')
  start(prompt.value.trim())
}

watch(finalOutput, (val) => {
  if (val) toast.success('Task completed.')
})

watch(runError, (val) => {
  if (val) toast.error(val.length > 80 ? val.slice(0, 80) + '…' : val)
})

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

    <div class="flex gap-1 rounded-lg bg-zinc-900 p-1 md:hidden overflow-x-auto">
      <button
        v-for="tab in ['chat', 'agents', 'plan', 'changes', 'audit', 'memory'] as const"
        :key="tab"
        type="button"
        class="shrink-0 rounded-md px-3 py-2 text-xs font-medium capitalize"
        :class="mobileTab === tab ? 'bg-zinc-800 text-zinc-100 shadow' : 'text-zinc-500'"
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
          <!-- Error display -->
          <div v-if="runError" class="mt-3 rounded-md border border-rose-800 bg-rose-950/50 px-4 py-3 text-sm text-rose-300">
            <span class="font-semibold text-rose-400">Error — </span>{{ runError }}
          </div>
        </section>

        <!-- AI response panel -->
        <section v-if="finalOutput" class="rounded-lg border border-emerald-800/50 bg-zinc-900 p-4 space-y-2">
          <h2 class="text-xs font-semibold uppercase text-emerald-500 tracking-wider">AI Response</h2>
          <pre class="whitespace-pre-wrap text-sm text-zinc-100 leading-relaxed font-mono">{{ finalOutput }}</pre>
        </section>

        <!-- Chat conversation -->
        <section class="space-y-4" aria-live="polite">
          <div
            v-if="artifacts.agentMessages.length === 0 && !running"
            class="flex flex-col items-center justify-center py-16 text-center"
          >
            <span class="text-4xl mb-3">🤖</span>
            <p class="text-sm text-zinc-400">Describe a task and hit <span class="font-semibold text-emerald-400">Run task</span>.</p>
            <p class="text-xs text-zinc-600 mt-1">The orchestrator, executor, auditor, and reviewer will respond here.</p>
          </div>
          <AgentMessageCard
            v-for="(message, idx) in artifacts.agentMessages"
            :key="message.id"
            :message="message"
            :is-last="idx === artifacts.agentMessages.length - 1"
            :is-running="running"
          />
        </section>

        <FinalResultPanel v-if="artifacts.finalResult.raw" :result="artifacts.finalResult" />
      </main>

      <aside class="space-y-4">
        <!-- Desktop: tab bar for right panel -->
        <div class="hidden md:flex gap-1 rounded-lg bg-zinc-900 p-1">
          <button
            v-for="tab in ['agents', 'plan', 'changes', 'audit', 'memory'] as const"
            :key="tab"
            type="button"
            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium capitalize transition-colors"
            :class="mobileTab === tab ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-500 hover:text-zinc-300'"
            @click="mobileTab = tab"
          >
            {{ tab === 'agents' ? '🤖 Agents' : tab === 'plan' ? '📋 Plan' : tab === 'changes' ? '📁 Changes' : tab === 'audit' ? '🔍 Audit' : '🧠 Memory' }}
          </button>
        </div>

        <!-- Agents activity feed -->
        <div :class="mobileTab !== 'agents' ? 'hidden' : ''" class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
          <AgentActivityFeed :events="events as Record<string, unknown>[]" :running="running" />
        </div>

        <div :class="mobileTab !== 'plan' ? 'hidden' : ''">
          <PlanChecklist :items="artifacts.checklist" />
        </div>
        <div :class="mobileTab !== 'changes' ? 'hidden' : ''">
          <ChangeTrackerPanel
            :files-read="artifacts.filesRead"
            :files-changed="artifacts.filesChanged"
            :commands-run="artifacts.commandsRun"
            :tests-run="artifacts.testsRun"
          />
        </div>
        <div :class="mobileTab !== 'audit' ? 'hidden' : ''">
          <AuditFindingsPanel
            :status="artifacts.finalResult.auditResult"
            :findings="artifacts.auditFindings"
          />
        </div>
        <div :class="mobileTab !== 'memory' ? 'hidden' : ''">
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
