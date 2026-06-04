<script setup lang="ts">
import { ref } from 'vue'
import type { ChatTurn } from '~/composables/useLandingChat'
import type { AgentMessage } from '~/types/bossku'

export type ThreadTab = 'chat' | 'process'
export type PanelTab = 'agents' | 'plan' | 'changes' | 'audit' | 'memory'
export type LandingTab = ThreadTab | PanelTab

const props = withDefaults(
  defineProps<{
    modelValue: LandingTab
    turns: ChatTurn[]
    agentMessages: AgentMessage[]
    running?: boolean
  }>(),
  { running: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: LandingTab]
}>()

const tabs: Array<{ value: LandingTab; label: string; hint: string }> = [
  { value: 'chat', label: 'Chat', hint: 'User prompt and AI reply' },
  { value: 'process', label: 'Agent Process', hint: 'Planner, executor, audit, proof' },
  { value: 'agents', label: 'Agents', hint: 'Workflow and activity feed' },
  { value: 'plan', label: 'Plan', hint: 'Goal, flow, risks, to-dos' },
  { value: 'changes', label: 'Changes', hint: 'Files and commands' },
  { value: 'audit', label: 'Audit', hint: 'Findings and verdict' },
  { value: 'memory', label: 'Memory', hint: 'Context used' },
]

const chatScrollRef = ref<HTMLElement | null>(null)
const processScrollRef = ref<HTMLElement | null>(null)
const panelScrollRef = ref<HTMLElement | null>(null)

function selectTab(tab: LandingTab) {
  emit('update:modelValue', tab)
}

function scrollPaneToBottom(el: HTMLElement | null) {
  if (!el) return
  el.scrollTop = el.scrollHeight
}

function scrollChatToBottom() {
  scrollPaneToBottom(chatScrollRef.value)
}

function scrollProcessToBottom() {
  scrollPaneToBottom(processScrollRef.value)
}

function scrollActiveToBottom() {
  if (props.modelValue === 'chat') {
    scrollChatToBottom()
    return
  }
  if (props.modelValue === 'process') {
    scrollProcessToBottom()
    return
  }
  scrollPaneToBottom(panelScrollRef.value)
}

defineExpose({
  scrollChatToBottom,
  scrollProcessToBottom,
  scrollActiveToBottom,
})
</script>

<template>
  <div
    class="flex min-h-[320px] max-h-[min(70vh,720px)] flex-col"
    data-testid="landing-conversation-tabs"
  >
    <div class="shrink-0 border-b border-zinc-800/60 px-3 pt-3 pb-2">
      <div
        class="flex gap-1 overflow-x-auto rounded-lg border border-zinc-800 bg-zinc-950/70 p-1"
        role="tablist"
        aria-label="Conversation and run panels"
      >
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          role="tab"
          :aria-selected="props.modelValue === tab.value"
          class="shrink-0 rounded-md px-3 py-2 text-left text-xs transition-colors"
          :class="props.modelValue === tab.value
            ? 'bg-zinc-800 text-zinc-100 shadow'
            : 'text-zinc-500 hover:bg-zinc-900 hover:text-zinc-300'"
          @click="selectTab(tab.value)"
        >
          <span class="block font-semibold whitespace-nowrap">{{ tab.label }}</span>
          <span class="mt-0.5 hidden lg:block text-[11px] opacity-80 max-w-[8rem] truncate">
            {{ tab.hint }}
          </span>
        </button>
      </div>
    </div>

    <div
      v-if="props.modelValue === 'chat'"
      ref="chatScrollRef"
      class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3"
      data-testid="chat-thread-scroll"
      aria-label="Chat messages"
    >
      <div
        v-if="turns.length === 0"
        class="flex flex-col items-center justify-center py-16 text-center"
      >
        <span class="mb-3 text-4xl">🤖</span>
        <p class="text-sm text-zinc-400">
          Send a message, question, or task.
        </p>
        <p class="mt-1 text-xs text-zinc-600">
          Past threads live under
          <NuxtLink to="/conversations" class="text-emerald-500 hover:underline">Conversations</NuxtLink>.
          Use <span class="font-medium text-zinc-400">New session</span> to clear the page.
        </p>
      </div>

      <ChatTurnBubble
        v-for="turn in turns"
        :key="turn.id"
        :turn="turn"
      />
      <slot name="chat-plan" />
    </div>

    <div
      v-else-if="props.modelValue === 'process'"
      ref="processScrollRef"
      class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3"
      data-testid="agent-process-scroll"
      aria-label="Agent process"
    >
      <div
        v-if="!running && agentMessages.length === 0"
        class="flex flex-col items-center justify-center py-16 text-center"
      >
        <span class="mb-3 text-4xl">🧭</span>
        <p class="text-sm text-zinc-400">No agent process has been captured for this conversation yet.</p>
        <p class="mt-1 text-xs text-zinc-600">
          Run a task or open a saved conversation that has stored run events.
        </p>
      </div>

      <template v-else>
        <div class="space-y-4">
          <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
            Agent transcript
          </p>
          <AgentMessageCard
            v-for="(message, idx) in agentMessages"
            :key="message.id"
            :message="message"
            :is-last="idx === agentMessages.length - 1"
            :is-running="running"
          />
        </div>
      </template>
    </div>

    <div
      v-else
      ref="panelScrollRef"
      class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3"
      :data-testid="`landing-panel-${props.modelValue}`"
      :aria-label="tabs.find(t => t.value === props.modelValue)?.label"
    >
      <slot v-if="props.modelValue === 'agents'" name="agents" />
      <slot v-else-if="props.modelValue === 'plan'" name="plan" />
      <slot v-else-if="props.modelValue === 'changes'" name="changes" />
      <slot v-else-if="props.modelValue === 'audit'" name="audit" />
      <slot v-else-if="props.modelValue === 'memory'" name="memory" />
    </div>
  </div>
</template>
