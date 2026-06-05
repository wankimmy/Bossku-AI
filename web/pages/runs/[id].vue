<script setup lang="ts">
import type { Approval, UsageEvent, FeedbackItem } from '~/types/api'

definePageMeta({ layout: 'default' })

const route = useRoute()
const api = useApi()

const { data, pending } = await useFetch<Record<string, unknown>>(() => apiUrl(`/runs/${route.params.id}`), { server: false })

type Tab =
  | 'overview'
  | 'timeline'
  | 'conversation'
  | 'plan'
  | 'tool-calls'
  | 'file-changes'
  | 'memory'
  | 'audit'
  | 'usage'
  | 'feedback'

const tab = ref<Tab>('overview')

const steps = computed(() => Array.isArray(data.value?.steps) ? data.value.steps as Record<string, unknown>[] : [])
const metadata = computed(() => data.value?.metadata as Record<string, unknown> | undefined)
const artifacts = computed(() => {
  const all = [...steps.value]
  if (data.value?.final_output) {
    all.push({ type: 'run_completed', agent: 'final-reviewer', status: data.value.status ?? 'success', output: data.value.final_output })
  }
  return useRunArtifacts(all)
})

// Lazy-loaded sub-resource data
const approvals = ref<Approval[]>([])
const usageEvents = ref<UsageEvent[]>([])
const feedbackItems = ref<FeedbackItem[]>([])
const tabFetched = ref<Set<Tab>>(new Set(['overview']))

async function switchTab(t: Tab) {
  tab.value = t
  if (tabFetched.value.has(t)) return
  const id = route.params.id as string
  try {
    if (t === 'usage') {
      usageEvents.value = (await api.get(`/runs/${id}/usage`)) as UsageEvent[]
    }
    else if (t === 'feedback') {
      feedbackItems.value = (await api.get(`/runs/${id}/feedback`)) as FeedbackItem[]
    }
    else if (t === 'audit') {
      approvals.value = (await api.get(`/approvals`, { run_id: id })) as Approval[]
    }
  }
  catch { /* sub-resource not available */ }
  tabFetched.value.add(t)
}

const tabs = [
  { id: 'overview' as Tab, label: 'Overview' },
  { id: 'timeline' as Tab, label: 'Timeline' },
  { id: 'conversation' as Tab, label: 'Agent Conversation' },
  { id: 'plan' as Tab, label: 'Plan' },
  { id: 'tool-calls' as Tab, label: 'Tool Calls' },
  { id: 'file-changes' as Tab, label: 'File Changes' },
  { id: 'memory' as Tab, label: 'Memory' },
  { id: 'audit' as Tab, label: 'Audit' },
  { id: 'usage' as Tab, label: 'Usage' },
  { id: 'feedback' as Tab, label: 'Feedback' },
] as const

const signalEmoji = (s: string) => {
  switch (s) {
    case 'positive': return '👍'
    case 'negative': return '👎'
    default: return '➖'
  }
}
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
      <h1 class="text-xl font-semibold text-zinc-100">
        Run <span class="font-mono text-sm text-zinc-500">{{ String(data.id ?? route.params.id).slice(0, 8) }}</span>
      </h1>
      <p class="mt-2 whitespace-pre-wrap rounded-lg border border-zinc-700 bg-zinc-900 p-4 text-sm text-zinc-300">
        {{ data.prompt }}
      </p>
    </div>

    <div class="flex gap-1 overflow-x-auto rounded-lg bg-zinc-900 p-1">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="min-w-fit rounded-md px-3 py-2 text-xs font-medium whitespace-nowrap"
        :class="tab === item.id ? 'bg-zinc-800 text-zinc-100 shadow' : 'text-zinc-500 hover:text-zinc-300'"
        @click="switchTab(item.id)"
      >
        {{ item.label }}
      </button>
    </div>

    <!-- Overview -->
    <section v-if="tab === 'overview'" class="grid gap-4 lg:grid-cols-2">
      <RoutingDashboard :metadata="metadata" />
      <FinalResultPanel :result="artifacts.finalResult" />
      <AgentHandoffFlow class="lg:col-span-2" :nodes="artifacts.handoffNodes" />
    </section>

    <!-- Timeline -->
    <section v-else-if="tab === 'timeline'">
      <ExecutionTimeline :events="steps" />
    </section>

    <!-- Agent Conversation -->
    <section v-else-if="tab === 'conversation'" class="space-y-3">
      <AgentMessageCard
        v-for="message in artifacts.agentMessages"
        :key="message.id"
        :message="message"
      />
      <UiEmptyState v-if="artifacts.agentMessages.length === 0" title="No conversation events." hint="This older run may only have raw JSON." />
    </section>

    <!-- Plan -->
    <section v-else-if="tab === 'plan'">
      <PlanOverview :plan="artifacts.plan" />
    </section>

    <!-- Tool Calls -->
    <section v-else-if="tab === 'tool-calls'" class="space-y-3">
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <h2 class="text-sm font-semibold text-zinc-100 mb-3">Commands Run</h2>
        <div v-if="artifacts.commandsRun.length === 0" class="text-sm text-zinc-500">No tool calls recorded.</div>
        <div v-else class="space-y-2">
          <div
            v-for="(cmd, i) in artifacts.commandsRun"
            :key="i"
            class="rounded bg-zinc-800 border border-zinc-700 px-3 py-2"
          >
            <div class="flex items-center justify-between gap-2">
              <code class="text-xs text-zinc-200 font-mono">{{ cmd.command }}</code>
              <span
                class="text-xs"
                :class="cmd.status === 'success' || cmd.exit_code === 0 ? 'text-emerald-400' : 'text-red-400'"
              >
                {{ cmd.status }} {{ cmd.exit_code != null ? `(exit ${cmd.exit_code})` : '' }}
              </span>
            </div>
            <div v-if="cmd.output_summary" class="text-xs text-zinc-500 mt-1">{{ cmd.output_summary }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- File Changes -->
    <section v-else-if="tab === 'file-changes'">
      <ChangeTrackerPanel
        :files-read="artifacts.filesRead"
        :files-changed="artifacts.filesChanged"
        :commands-run="[]"
        :tests-run="artifacts.testsRun"
      />
    </section>

    <!-- Memory -->
    <section v-else-if="tab === 'memory'">
      <ContextDrawer title="Context / Memory Used" :context-events="steps" />
    </section>

    <!-- Audit -->
    <section v-else-if="tab === 'audit'" class="space-y-4">
      <AuditFindingsPanel :status="artifacts.finalResult.auditResult" :findings="artifacts.auditFindings" />
      <TestResultPanel :commands-run="artifacts.commandsRun" :tests-run="artifacts.testsRun" />
      <div v-if="approvals.length > 0" class="space-y-2">
        <h2 class="text-sm font-semibold text-zinc-100">Approvals</h2>
        <div
          v-for="approval in approvals"
          :key="approval.id"
          class="rounded-lg border border-zinc-700 bg-zinc-900 p-3 flex items-center justify-between gap-3"
        >
          <div>
            <span class="font-mono text-xs text-zinc-300">{{ approval.operation_type }}</span>
            <p v-if="approval.description" class="text-xs text-zinc-500 mt-0.5">{{ approval.description }}</p>
          </div>
          <span
            class="text-xs px-2 py-0.5 rounded border"
            :class="{
              'bg-emerald-900/50 text-emerald-300 border-emerald-800': approval.status === 'approved',
              'bg-red-900/50 text-red-300 border-red-800': approval.status === 'rejected',
              'bg-yellow-900/50 text-yellow-300 border-yellow-800': approval.status === 'pending',
            }"
          >{{ approval.status }}</span>
        </div>
      </div>
    </section>

    <!-- Usage -->
    <section v-else-if="tab === 'usage'">
      <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-800">
          <h2 class="text-sm font-semibold text-zinc-100">Usage for this run</h2>
        </div>
        <div v-if="usageEvents.length === 0" class="px-4 py-6 text-sm text-zinc-500 text-center">No usage data.</div>
        <table v-else class="w-full text-xs">
          <thead>
            <tr class="border-b border-zinc-800">
              <th class="px-4 py-2 text-left text-zinc-500">Model</th>
              <th class="px-4 py-2 text-left text-zinc-500">Tokens</th>
              <th class="px-4 py-2 text-left text-zinc-500">Cost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ev in usageEvents" :key="ev.id" class="border-b border-zinc-800/50">
              <td class="px-4 py-2 font-mono text-zinc-300">{{ ev.model ?? '—' }}</td>
              <td class="px-4 py-2 text-zinc-400">{{ ev.total_tokens?.toLocaleString() ?? '—' }}</td>
              <td class="px-4 py-2 text-emerald-400">{{ ev.cost != null ? `$${ev.cost.toFixed(6)}` : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Feedback -->
    <section v-else-if="tab === 'feedback'">
      <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-800">
          <h2 class="text-sm font-semibold text-zinc-100">Feedback for this run</h2>
        </div>
        <div v-if="feedbackItems.length === 0" class="px-4 py-6 text-sm text-zinc-500 text-center">No feedback submitted.</div>
        <ul v-else class="divide-y divide-zinc-800">
          <li v-for="item in feedbackItems" :key="item.id" class="px-4 py-3 flex items-start gap-3">
            <span class="text-lg">{{ signalEmoji(item.signal) }}</span>
            <div>
              <p v-if="item.comment" class="text-sm text-zinc-300">{{ item.comment }}</p>
              <p class="text-xs text-zinc-500 mt-0.5">{{ item.target_type }} · {{ item.created_at ? new Date(item.created_at).toLocaleString() : '' }}</p>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>
