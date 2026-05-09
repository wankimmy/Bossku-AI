<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const base = useApiBase()
const { data, pending } = await useFetch<Record<string, unknown>>(() => `${base}/api/runs/${route.params.id}`, { server: false })
const tab = ref<'overview' | 'conversation' | 'plan' | 'changes' | 'audit' | 'raw'>('overview')

const steps = computed(() => Array.isArray(data.value?.steps) ? data.value.steps as Record<string, unknown>[] : [])
const metadata = computed(() => data.value?.metadata as Record<string, unknown> | undefined)
const artifacts = computed(() => {
  const all = [...steps.value]
  if (data.value?.final_output) {
    all.push({ type: 'run_completed', agent: 'final-reviewer', status: data.value.status ?? 'success', output: data.value.final_output })
  }
  return useRunArtifacts(all)
})

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'conversation', label: 'Conversation' },
  { id: 'plan', label: 'Plan' },
  { id: 'changes', label: 'Changes' },
  { id: 'audit', label: 'Audit' },
  { id: 'raw', label: 'Raw JSON' },
] as const
</script>

<template>
  <UiSkeleton v-if="pending" class="h-64 w-full" />
  <div v-else-if="data" class="space-y-4">
    <RunStatusHeader
      :status="String(data.status || 'unknown')"
      :memory-used="artifacts.memoryUsed"
      :routing="artifacts.routingSummary"
    />

    <div>
      <h1 class="text-xl font-semibold">
        Run detail
      </h1>
      <p class="mt-2 whitespace-pre-wrap rounded-lg border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
        {{ data.prompt }}
      </p>
    </div>

    <div class="flex gap-1 overflow-x-auto rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="min-w-fit rounded-md px-3 py-2 text-sm font-medium"
        :class="tab === item.id ? 'bg-white text-zinc-900 shadow dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400'"
        @click="tab = item.id"
      >
        {{ item.label }}
      </button>
    </div>

    <section v-if="tab === 'overview'" class="grid gap-4 lg:grid-cols-2">
      <RoutingDashboard :metadata="metadata" />
      <FinalResultPanel :result="artifacts.finalResult" />
      <AgentHandoffFlow class="lg:col-span-2" :nodes="artifacts.handoffNodes" />
    </section>

    <section v-else-if="tab === 'conversation'" class="space-y-3">
      <AgentMessageCard
        v-for="message in artifacts.agentMessages"
        :key="message.id"
        :message="message"
      />
      <UiEmptyState v-if="artifacts.agentMessages.length === 0" title="No conversation events." hint="This older run may only have raw JSON." />
    </section>

    <PlanChecklist v-else-if="tab === 'plan'" :items="artifacts.checklist" />

    <ChangeTrackerPanel
      v-else-if="tab === 'changes'"
      :files-read="artifacts.filesRead"
      :files-changed="artifacts.filesChanged"
      :commands-run="artifacts.commandsRun"
      :tests-run="artifacts.testsRun"
    />

    <section v-else-if="tab === 'audit'" class="space-y-4">
      <AuditFindingsPanel :status="artifacts.finalResult.auditResult" :findings="artifacts.auditFindings" />
      <TestResultPanel :commands-run="artifacts.commandsRun" :tests-run="artifacts.testsRun" />
    </section>

    <section v-else class="space-y-3">
      <JsonViewer :data="data" />
      <div class="space-y-2">
        <JsonViewer v-for="s in steps" :key="String(s.id)" :data="s" />
      </div>
    </section>
  </div>
</template>
