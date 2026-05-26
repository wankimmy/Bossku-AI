<script setup lang="ts">
import { useLandingChat } from '~/composables/useLandingChat'
import { useSkills } from '~/composables/useSkills'
import { isAwaitingApprovals } from '~/utils/approvalStream'
import { parseClarificationApiResponse } from '~/utils/clarificationFromApi'
import {
  buildProjectUnderstandingPrompt,
  buildSlashCommandItems,
  filterSlashCommandItems,
  findSlashTrigger,
  replaceSlashTrigger,
  type SlashCommandItem,
  type SlashTrigger,
} from '~/utils/slashCommands'
import type { ClarificationRequest } from '~/types/clarification'
import type { Approval } from '~/types/api'

definePageMeta({ layout: 'default' })

const route = useRoute()
const router = useRouter()
const prompt = ref('')
const promptInput = ref<HTMLTextAreaElement | null>(null)
const chat = useLandingChat()
const skills = useSkills()
const {
  events,
  running,
  error,
  awaitingClarification,
  clarificationRequest,
  activeRunId,
  start,
  continueRun,
  continueAfterApprovals,
  stop,
} = useRunStream()
const runApprovals = useRunApprovals()
const submittingClarification = ref(false)
const continuingApprovals = ref(false)
const apiClarificationRequest = ref<ClarificationRequest | null>(null)
const apiClarificationLoading = ref(false)
const apiClarificationFetchedFor = ref<string | null>(null)
const toast = useToast()
type PanelTab = 'agents' | 'plan' | 'changes' | 'audit' | 'memory'
type MobileTab = 'chat' | PanelTab
type ThreadTab = 'chat' | 'process'

const rightPanelTab = ref<PanelTab>('agents')
const mobileTab = ref<MobileTab>('chat')
const threadTab = ref<ThreadTab>('chat')
const slashTrigger = ref<SlashTrigger | null>(null)
const slashActiveIndex = ref(0)

const panelTabs: PanelTab[] = ['agents', 'plan', 'changes', 'audit', 'memory']
const mobileTabs: MobileTab[] = ['chat', ...panelTabs]

function panelTabLabel(tab: PanelTab) {
  if (tab === 'agents') return '🤖 Agents'
  if (tab === 'plan') return '📋 Plan'
  if (tab === 'changes') return '📁 Changes'
  if (tab === 'audit') return '🔍 Audit'
  return '🧠 Memory'
}

function showRightPanel(tab: PanelTab) {
  return rightPanelTab.value === tab
}

function showMobilePanel(tab: PanelTab) {
  return mobileTab.value === tab
}
const slashItems = computed(() => buildSlashCommandItems(skills.data.value ?? []))
const slashGroups = computed(() => filterSlashCommandItems(slashItems.value, slashTrigger.value?.query ?? ''))
const slashVisibleItems = computed(() => [
  ...slashGroups.value.essential,
  ...slashGroups.value.skills,
])
const slashMenuGroups = computed(() => {
  let index = 0
  return [
    ...(slashGroups.value.essential.length
      ? [{
          label: 'Essential',
          items: slashGroups.value.essential.map(item => ({ item, index: index++ })),
        }]
      : []),
    ...(slashGroups.value.skills.length
      ? [{
          label: 'Skills',
          items: slashGroups.value.skills.map(item => ({ item, index: index++ })),
        }]
      : []),
  ]
})
watch(
  prompt,
  (value) => {
    const text = String(value ?? '')
    if (text.startsWith('/')) {
      slashTrigger.value = {
        start: 0,
        end: text.length,
        query: text.slice(1),
      }
      slashActiveIndex.value = 0
      return
    }
    if (!promptInput.value) return
    syncSlashTriggerFromTextarea(promptInput.value)
  },
  { flush: 'post' },
)
const lastRecordedRunId = ref<string | null>(null)

const artifacts = computed(() => useRunArtifacts(events.value as Record<string, unknown>[]))
const { configured: configuredRouting } = useConfiguredRouting()
const displayRouting = computed(() =>
  mergeRoutingSummary(artifacts.value.routingSummary, configuredRouting.value),
)
const status = computed(() => {
  const last = events.value.at(-1)
  return last ? String(last.status ?? last.type ?? 'running') : 'idle'
})

// Final AI response and error surfacing
const finalOutput = computed(() => {
  const done = events.value.findLast((e: Record<string, unknown>) => e.type === 'run_completed')
  return done ? String(done.output ?? done.final_output ?? '') : ''
})
const awaitingApprovalsFromEvents = computed(() => isAwaitingApprovals(events.value))

const runError = computed(() => {
  if (runApprovals.awaitingApprovals.value || awaitingApprovalsFromEvents.value) {
    return ''
  }
  if (error.value) {
    const err = String(error.value)
    if (err.includes('continue-approvals')) return ''
    return err
  }
  const failed = events.value.findLast((e: Record<string, unknown>) => e.type === 'run_failed')
  if (!failed) return ''
  const msg = String(
    failed.error ?? failed.message ?? failed.summary ?? '',
  )
  if (msg.includes('continue-approvals') || msg.includes('change approvals')) {
    return ''
  }
  const plannerFailed = events.value.findLast((e: Record<string, unknown>) => e.type === 'planner_failed')
  return String(
    failed.error ?? failed.message ?? plannerFailed?.error ?? plannerFailed?.message ?? failed.summary ?? 'Run failed.',
  )
})

function recordAssistantReply(content: string) {
  const text = content.trim()
  if (!text) return
  const recordKey = assistantRecordKey(text)
  if (lastRecordedRunId.value === recordKey) return
  lastRecordedRunId.value = recordKey
  chat.addAssistantTurn(text)
  chat.saveRunEvents(events.value)
}

function assistantRecordKey(content: string) {
  const runId = String(events.value.find(e => e.run_id)?.run_id ?? '')
  if (runId) return runId
  return `conv:${chat.activeId.value ?? 'local'}:${content}`
}

watch(running, (isRunning) => {
  if (isRunning) focusAgentsPanel()
})

watch(
  () => [running.value, finalOutput.value, runError.value, awaitingClarification.value, awaitingApprovalsFromEvents.value, runApprovals.awaitingApprovals.value] as const,
  ([isRunning, output, err, awaitingClar, awaitingAppr, awaitingApprState]) => {
    if (isRunning) return
    chat.saveRunEvents(events.value)
    if (awaitingClar || awaitingAppr || awaitingApprState) return
    if (output) {
      recordAssistantReply(output)
      toast.success('Task completed.')
      return
    }
    if (err) recordAssistantReply(`Error: ${err}`)
  },
)

function focusAgentsPanel() {
  rightPanelTab.value = 'agents'
  mobileTab.value = 'agents'
  threadTab.value = 'process'
}

async function syncRun() {
  const userText = prompt.value.trim()
  if (!userText) return
  focusAgentsPanel()
  const prior = chat.conversationPayload()
  chat.addUserTurn(userText)
  syncConvQuery()
  prompt.value = ''
  closeSlashMenu()
  try {
    const res = await $fetch<{ final_output?: string }>(apiUrl('/runs'), {
      method: 'POST',
      body: { prompt: userText, conversation: prior },
    })
    const out = res.final_output || 'Completed'
    events.value.push({
      type: 'run_completed',
      agent: 'final-reviewer',
      status: 'success',
      output: out,
    })
    recordAssistantReply(out)
  }
  catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e)
    events.value.push({
      type: 'run_failed',
      agent: 'system',
      status: 'fail',
      summary: 'Run failed.',
      message: msg,
    })
    recordAssistantReply(`Error: ${msg}`)
  }
}

function closeSlashMenu() {
  slashTrigger.value = null
  slashActiveIndex.value = 0
}

function syncSlashTriggerFromTextarea(textarea: HTMLTextAreaElement | null) {
  if (!textarea) {
    closeSlashMenu()
    return
  }

  const cursor = textarea.selectionStart ?? textarea.value.length
  const trigger = findSlashTrigger(textarea.value, cursor)
    ?? (textarea.value.startsWith('/') ? findSlashTrigger(textarea.value, textarea.value.length) : null)
  slashTrigger.value = trigger
  if (!trigger) {
    slashActiveIndex.value = 0
  }
}

function syncSlashTriggerFromEvent(event: Event) {
  syncSlashTriggerFromTextarea(event.target instanceof HTMLTextAreaElement ? event.target : null)
}

function syncSlashTriggerFromSelection() {
  syncSlashTriggerFromTextarea(promptInput.value)
}

function selectSlashItem(item: SlashCommandItem) {
  if (!slashTrigger.value) return
  const source = promptInput.value?.value ?? prompt.value
  const replaced = replaceSlashTrigger(source, slashTrigger.value, item.insert)
  prompt.value = replaced.value
  closeSlashMenu()
  nextTick(() => {
    promptInput.value?.focus()
    if (promptInput.value) {
      promptInput.value.setSelectionRange(replaced.cursor, replaced.cursor)
    }
  })
}

function runSlashSelection() {
  const item = slashVisibleItems.value[slashActiveIndex.value]
  if (item) selectSlashItem(item)
}

function onPromptKeydown(event: KeyboardEvent) {
  if (showSlashMenu.value) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      slashActiveIndex.value = Math.min(slashActiveIndex.value + 1, Math.max(slashVisibleItems.value.length - 1, 0))
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      slashActiveIndex.value = Math.max(slashActiveIndex.value - 1, 0)
      return
    }
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      runSlashSelection()
      return
    }
    if (event.key === 'Escape') {
      event.preventDefault()
      closeSlashMenu()
      return
    }
  }

  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    submit()
  }
}

function clearInsertQuery() {
  const nextQuery: Record<string, string> = {}
  const conv = route.query.conv
  if (typeof conv === 'string' && conv.trim()) {
    nextQuery.conv = conv
  }
  router.replace({ path: '/', query: nextQuery })
}

function applyRouteInsertPrefill() {
  const insert = route.query.insert
  if (insert !== PROJECT_UNDERSTANDING_COMMAND) return
  if (prompt.value.trim()) return
  closeSlashMenu()
  prompt.value = buildProjectUnderstandingPrompt()
  nextTick(() => promptInput.value?.focus())
  clearInsertQuery()
}

watch(
  () => [chat.hydrated.value, route.query.insert, route.query.conv] as const,
  ([hydrated]) => {
    if (!hydrated || running.value) return
    applyConversationFromRoute()
    applyRouteInsertPrefill()
  },
)

const effectiveClarificationRequest = computed((): ClarificationRequest | null =>
  clarificationRequest.value ?? apiClarificationRequest.value,
)

const showClarificationModal = computed(
  () => effectiveClarificationRequest.value !== null
    && !runApprovals.awaitingApprovals.value
    && !awaitingApprovalsFromEvents.value,
)

const showApprovalModal = computed(
  () => runApprovals.awaitingApprovals.value && runApprovals.current.value !== null,
)

const showSlashMenu = computed(() =>
  slashTrigger.value !== null
  && !showClarificationModal.value
  && !showApprovalModal.value
  && !running.value
  && !submittingClarification.value,
)

const resolvedRunId = computed(() =>
  activeRunId.value
  ?? String(events.value.find(e => e.run_id)?.run_id ?? ''),
)

watch(
  () => events.value.filter((e: Record<string, unknown>) => e.type === 'approval_requested').length,
  async () => {
    const approvalEvt = events.value.findLast(
      (e: Record<string, unknown>) => e.type === 'approval_requested',
    )
    if (approvalEvt) {
      runApprovals.seedFromSseEvent(approvalEvt)
      const runId = resolvedRunId.value
      if (runId) {
        await runApprovals.fetchPending(runId)
      }
    }
  },
)

watchEffect(async () => {
  const runId = resolvedRunId.value
  const needsApprovals = awaitingApprovalsFromEvents.value
    || events.value.some((e: Record<string, unknown>) => e.type === 'approval_requested')

  if (needsApprovals && runId && !awaitingClarification.value) {
    await runApprovals.fetchPending(runId)
  }
})

watchEffect(async () => {
  const runId = resolvedRunId.value
  const awaiting = awaitingClarification.value
    || (
      status.value === 'awaiting_input'
      && !runApprovals.awaitingApprovals.value
      && !awaitingApprovalsFromEvents.value
    )

  if (!awaiting || !runId) {
    apiClarificationRequest.value = null
    apiClarificationFetchedFor.value = null
    return
  }

  if (clarificationRequest.value !== null) {
    apiClarificationRequest.value = null
    apiClarificationFetchedFor.value = null
    return
  }

  if (apiClarificationFetchedFor.value === runId || apiClarificationLoading.value) {
    return
  }

  apiClarificationFetchedFor.value = runId
  apiClarificationLoading.value = true
  try {
    const data = await $fetch<Record<string, unknown>>(apiUrl(`/runs/${runId}/clarification`))
    if (resolvedRunId.value !== runId) return
    apiClarificationRequest.value = parseClarificationApiResponse(data, runId)
  }
  catch {
    apiClarificationRequest.value = null
  }
  finally {
    apiClarificationLoading.value = false
  }
})

async function onApprovalDecided() {
  const runId = resolvedRunId.value
  if (!runId) return
  runApprovals.shiftQueue()
  await runApprovals.fetchPending(runId)
  if (runApprovals.pending.value.length > 0) return
  runApprovals.clear()
  continuingApprovals.value = true
  focusAgentsPanel()
  toast.info('Continuing run with your decisions…')
  try {
    await continueAfterApprovals(runId)
    chat.saveRunEvents(events.value)
  }
  finally {
    continuingApprovals.value = false
  }
}

async function submitClarification(
  answers: Array<{ question_id: string; option_id?: string; free_text?: string }>,
) {
  const req = effectiveClarificationRequest.value
  if (!req?.runId || submittingClarification.value) return
  submittingClarification.value = true
  focusAgentsPanel()
  toast.info('Continuing run with your answers…')
  try {
    await continueRun(req.runId, answers)
    chat.saveRunEvents(events.value)
  }
  finally {
    submittingClarification.value = false
  }
}

function submit() {
  const userText = prompt.value.trim()
  if (running.value || submittingClarification.value) return

  if (showClarificationModal.value || showApprovalModal.value) {
    return
  }

  if (!userText) return
  focusAgentsPanel()
  const prior = chat.conversationPayload()
  chat.addUserTurn(userText)
  syncConvQuery()
  prompt.value = ''
  closeSlashMenu()
  lastRecordedRunId.value = null
  toast.info('Task started…')
  start(userText, { conversation: prior })
}

function assistantOutputFromEvents(evts: Record<string, unknown>[]): string {
  const done = evts.findLast(e => e.type === 'run_completed')
  if (!done) return ''
  return String(done.output ?? done.final_output ?? '').trim()
}

/** If we restored SSE events but no AI bubble yet, add one so order is You → AI → agent activity. */
function backfillAssistantFromEvents(evts: Record<string, unknown>[]) {
  const text = assistantOutputFromEvents(evts)
  if (!text) return
  const turns = chat.turns.value
  const last = turns.at(-1)
  if (last?.role !== 'user') return
  const already = turns.some(t => t.role === 'assistant' && t.content.trim() === text)
  if (!already) chat.addAssistantTurn(text)
}

function applyConversationFromRoute() {
  closeSlashMenu()
  const raw = route.query.conv
  const convId = typeof raw === 'string' ? raw : null
  if (convId) {
    if (!chat.selectConversation(convId)) {
      toast.warning('Conversation not found.')
      chat.clearActiveConversation()
      events.value = []
      router.replace({ path: '/', query: {} })
      return
    }
    events.value = chat.getRunEvents(convId)
    backfillAssistantFromEvents(events.value as Record<string, unknown>[])
    if (events.value.length > 0) focusAgentsPanel()
    const restoredOutput = assistantOutputFromEvents(events.value as Record<string, unknown>[])
    lastRecordedRunId.value = restoredOutput ? assistantRecordKey(restoredOutput) : null
    return
  }
  chat.clearActiveConversation()
  events.value = []
  lastRecordedRunId.value = null
  closeSlashMenu()
}

function syncConvQuery() {
  const id = chat.activeId.value
  if (id && route.query.conv !== id) {
    router.replace({ path: '/', query: { conv: id } })
  }
}

/** Blank front-page chat; prior threads stay under /conversations until you send a message. */
function newSession() {
  if (running.value) stop()
  chat.clearActiveConversation()
  events.value = []
  lastRecordedRunId.value = null
  prompt.value = ''
  closeSlashMenu()
  mobileTab.value = 'chat'
  threadTab.value = 'chat'
  router.replace({ path: '/', query: {} })
  toast.info('New session — previous messages hidden until you open them in Conversations.')
}

const hasActiveSession = computed(
  () => Boolean(route.query.conv) || chat.turns.value.length > 0 || events.value.length > 0 || running.value,
)

watch(runError, (val) => {
  if (val && !running.value) toast.error(val.length > 80 ? val.slice(0, 80) + '…' : val)
})

const chatThreadRef = ref<HTMLElement | null>(null)

function scrollChatToBottom() {
  nextTick(() => {
    const el = chatThreadRef.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

watch(
  () => [chat.turns.value.length, events.value.length, artifacts.value.agentMessages.length, running.value, threadTab.value] as const,
  () => scrollChatToBottom(),
)

watch(showClarificationModal, (open) => {
  if (!open) return
  mobileTab.value = 'chat'
})

watch(showApprovalModal, async (open) => {
  if (!open) return
  mobileTab.value = 'chat'
  const runId = resolvedRunId.value
  if (runId) {
    await runApprovals.fetchPending(runId)
  }
})

onMounted(() => {
  chat.hydrateFromStorage()
})
</script>

<template>
  <div class="space-y-4 pb-28 md:pb-6">
    <RunStatusHeader
      :running="running"
      :status="status"
      :memory-used="artifacts.memoryUsed"
      :routing="displayRouting"
    />

    <div class="flex gap-1 rounded-lg bg-zinc-900 p-1 md:hidden overflow-x-auto">
      <button
        v-for="tab in mobileTabs"
        :key="tab"
        type="button"
        class="shrink-0 rounded-md px-3 py-2 text-xs font-medium capitalize"
        :class="mobileTab === tab ? 'bg-zinc-800 text-zinc-100 shadow' : 'text-zinc-500'"
        @click="mobileTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
      <main :class="mobileTab !== 'chat' ? 'hidden md:block' : ''" class="space-y-4">
        <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="run-prompt" class="text-sm font-semibold">Message</label>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="rounded-md border border-emerald-700/60 bg-emerald-950/30 px-2.5 py-1 text-xs font-medium text-emerald-300 hover:bg-emerald-900/40 disabled:opacity-50"
                :disabled="running"
                @click="newSession"
              >
                New session
              </button>
              <NuxtLink
                to="/conversations"
                class="text-xs text-zinc-500 hover:text-emerald-400"
              >
                Past conversations
                <span
                  v-if="chat.conversations.value.length > 0"
                  class="ml-1 rounded-full bg-zinc-800 px-1.5 py-0.5 text-[10px] text-zinc-400"
                >
                  {{ chat.conversations.value.length }}
                </span>
              </NuxtLink>
            </div>
          </div>
          <p v-if="hasActiveSession && chat.turns.value.length > 0" class="mt-1 text-xs text-zinc-500">
            Chat shows user/AI turns. Agent Process shows the planner, executor, audit, proof, and handoffs for this conversation.
            <button type="button" class="ml-1 text-emerald-500 hover:underline" @click="newSession">
              Start fresh
            </button>
          </p>
          <p
            v-if="showClarificationModal"
            class="mt-2 rounded-md border border-amber-500/30 bg-amber-950/20 px-3 py-2 text-xs text-amber-200/90"
          >
            Answer in the popup (3 choices + text field), then press Continue.
          </p>
          <textarea
            id="run-prompt"
            data-tour="chat-prompt"
            ref="promptInput"
            v-model="prompt"
            class="mt-2 block min-h-[110px] w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="showClarificationModal"
            role="combobox"
            aria-controls="slash-menu"
            :aria-expanded="showSlashMenu"
            :aria-activedescendant="showSlashMenu ? `slash-opt-${slashActiveIndex}` : undefined"
            :placeholder="showClarificationModal
              ? 'Answer in the clarification popup to continue…'
              : 'Describe the engineering task or type / for skills...'"
            @input="syncSlashTriggerFromEvent"
            @click="syncSlashTriggerFromSelection"
            @keyup="syncSlashTriggerFromSelection"
            @keydown="onPromptKeydown"
          />
          <div
            v-if="showSlashMenu"
            id="slash-menu"
            role="listbox"
            aria-label="Slash commands"
            class="mt-2 rounded-lg border border-zinc-800 bg-zinc-950/95 p-2 shadow-xl shadow-black/30"
          >
            <div class="flex items-center justify-between gap-2 px-1 pb-2 text-[11px] uppercase tracking-[0.25em] text-zinc-500">
              <span>Slash commands</span>
              <span v-if="slashTrigger?.query">/{{ slashTrigger?.query }}</span>
            </div>
            <div v-if="slashVisibleItems.length === 0" class="px-2 py-3 text-sm text-zinc-500">
              No matching skills.
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="group in slashMenuGroups"
                :key="group.label"
                class="space-y-1"
              >
                <p class="px-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  {{ group.label }}
                </p>
                <button
                  v-for="{ item, index } in group.items"
                  :key="item.id"
                  :id="`slash-opt-${index}`"
                  type="button"
                  role="option"
                  :aria-selected="index === slashActiveIndex"
                  class="flex w-full items-start justify-between gap-3 rounded-md px-2 py-2 text-left transition-colors"
                  :class="index === slashActiveIndex
                    ? 'bg-emerald-900/40 text-emerald-100'
                    : 'text-zinc-300 hover:bg-zinc-900'"
                  @click="selectSlashItem(item)"
                  @mousemove="slashActiveIndex = index"
                >
                  <span class="min-w-0 flex-1">
                    <span class="block font-medium">{{ item.label }}</span>
                    <span class="mt-0.5 block text-xs text-zinc-500">
                      {{ item.description }}
                    </span>
                  </span>
                  <span class="shrink-0 rounded-full border border-zinc-800 px-2 py-0.5 text-[10px] uppercase tracking-wide text-zinc-500">
                    {{ item.group }}
                  </span>
                </button>
              </div>
            </div>
          </div>
          <p class="mt-2 text-xs text-zinc-500">
            Tip: type <code class="rounded bg-zinc-950 px-1 text-zinc-300">/project-understanding</code> first on a new repo, then continue with a narrower skill.
          </p>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-700"
              :disabled="running || submittingClarification || showClarificationModal || showSlashMenu || !prompt.trim()"
              @click="submit"
            >
              Run task
            </button>
            <button
              type="button"
              class="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
              :disabled="running || showSlashMenu || !prompt.trim()"
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

        <!-- Conversation thread: chat and agent process are derived from the same saved run events. -->
        <section
          ref="chatThreadRef"
          class="max-h-[min(70vh,720px)] space-y-4 overflow-y-auto rounded-lg border border-zinc-800/60 bg-zinc-950/40 p-3"
          aria-live="polite"
        >
          <LandingConversationTabs
            v-model="threadTab"
            :turns="chat.displayTurns.value"
            :agent-messages="artifacts.agentMessages"
            :handoff-nodes="artifacts.handoffNodes"
            :running="running"
          />
        </section>

        <FinalResultPanel v-if="artifacts.finalResult.raw" :result="artifacts.finalResult" />
      </main>

      <aside class="space-y-4">
        <!-- Desktop: tab bar for right panel -->
        <div class="hidden md:flex gap-1 rounded-lg bg-zinc-900 p-1">
          <button
            v-for="tab in panelTabs"
            :key="tab"
            type="button"
            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium capitalize transition-colors"
            :class="rightPanelTab === tab ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-500 hover:text-zinc-300'"
            @click="rightPanelTab = tab"
          >
            {{ panelTabLabel(tab) }}
          </button>
        </div>

        <div
          class="space-y-3"
          :class="[
            showRightPanel('agents') ? 'md:block' : 'md:hidden',
            showMobilePanel('agents') ? 'max-md:block' : 'max-md:hidden',
          ]"
        >
          <AgentHandoffFlow layout="vertical" :nodes="artifacts.handoffNodes" />
          <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
            <AgentActivityFeed :events="events as Record<string, unknown>[]" :running="running" />
          </div>
        </div>

        <div
          :class="[
            showRightPanel('plan') ? 'md:block' : 'md:hidden',
            showMobilePanel('plan') ? 'max-md:block' : 'max-md:hidden',
          ]"
        >
          <PlanChecklist :items="artifacts.checklist" />
        </div>
        <div
          :class="[
            showRightPanel('changes') ? 'md:block' : 'md:hidden',
            showMobilePanel('changes') ? 'max-md:block' : 'max-md:hidden',
          ]"
        >
          <ChangeTrackerPanel
            :files-read="artifacts.filesRead"
            :files-changed="artifacts.filesChanged"
            :commands-run="artifacts.commandsRun"
            :tests-run="artifacts.testsRun"
          />
        </div>
        <div
          :class="[
            showRightPanel('audit') ? 'md:block' : 'md:hidden',
            showMobilePanel('audit') ? 'max-md:block' : 'max-md:hidden',
          ]"
        >
          <AuditFindingsPanel
            :status="artifacts.finalResult.auditResult"
            :findings="artifacts.auditFindings"
          />
        </div>
        <div
          :class="[
            showRightPanel('memory') ? 'md:block' : 'md:hidden',
            showMobilePanel('memory') ? 'max-md:block' : 'max-md:hidden',
          ]"
        >
          <ContextDrawer title="Context used" :context-events="events as Record<string, unknown>[]" />
        </div>
      </aside>
    </div>

    <ClarificationModal
      v-if="effectiveClarificationRequest"
      :open="showClarificationModal"
      :request="effectiveClarificationRequest"
      :submitting="submittingClarification || running"
      @submit="submitClarification"
    />

    <ChangeApprovalModal
      :open="showApprovalModal"
      :approval="runApprovals.current.value"
      :pending-count="runApprovals.pendingCount.value"
      :submitting="continuingApprovals || running"
      @approve="onApprovalDecided"
      @reject="onApprovalDecided"
    />

    <div
      v-if="!showClarificationModal && !showApprovalModal"
      class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950 md:hidden"
    >
      <button
        type="button"
        class="w-full rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-40"
        :disabled="running || showSlashMenu || !prompt.trim()"
        @click="submit"
      >
        Run task
      </button>
    </div>
  </div>
</template>
