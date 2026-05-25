<script setup lang="ts">
import type { ChatTurn } from '~/composables/useLandingChat'
import type { AgentMessage, HandoffNode } from '~/types/bossku'

type ThreadTab = 'chat' | 'process'

const props = withDefaults(
  defineProps<{
    modelValue: ThreadTab
    turns: ChatTurn[]
    agentMessages: AgentMessage[]
    handoffNodes: HandoffNode[]
    running?: boolean
  }>(),
  { running: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: ThreadTab]
}>()

const tabs: Array<{ value: ThreadTab; label: string; hint: string }> = [
  { value: 'chat', label: 'Chat', hint: 'User prompt and AI reply' },
  { value: 'process', label: 'Agent Process', hint: 'Planner, executor, audit, proof' },
]

function selectTab(tab: ThreadTab) {
  emit('update:modelValue', tab)
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-950/70 p-1">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="flex-1 rounded-md px-3 py-2 text-left text-xs transition-colors sm:flex-none sm:min-w-40"
        :class="props.modelValue === tab.value
          ? 'bg-zinc-800 text-zinc-100 shadow'
          : 'text-zinc-500 hover:bg-zinc-900 hover:text-zinc-300'"
        @click="selectTab(tab.value)"
      >
        <span class="block font-semibold">{{ tab.label }}</span>
        <span class="mt-0.5 block text-[11px] opacity-80">{{ tab.hint }}</span>
      </button>
    </div>

    <div v-if="props.modelValue === 'chat'" class="space-y-4">
      <div
        v-if="turns.length === 0"
        class="flex flex-col items-center justify-center py-16 text-center"
      >
        <span class="mb-3 text-4xl">🤖</span>
        <p class="text-sm text-zinc-400">
          Describe a task and hit <span class="font-semibold text-emerald-400">Run task</span>.
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
    </div>

    <div v-else class="space-y-4">
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
        <AgentHandoffFlow layout="horizontal" :nodes="handoffNodes" />
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
  </div>
</template>
