<script setup lang="ts">
import type { AgentMessage } from '../types/bossku'
import { themeForAgent } from '../utils/agentTheme'

const props = defineProps<{ message: AgentMessage; isLast?: boolean; isRunning?: boolean }>()

const cfg = computed(() => themeForAgent(props.message.agent))

const statusColor: Record<string, string> = {
  completed: 'bg-emerald-900 text-emerald-300',
  failed:    'bg-rose-900 text-rose-300',
  running:   'bg-blue-900 text-blue-300',
  pending:   'bg-zinc-800 text-zinc-400',
}

const mainText = computed(() =>
  props.message.summary
  || (props.message.status === 'failed' ? 'Run step failed.' : ''),
)

const showPlainDetail = computed(() =>
  props.message.message
  && !props.message.router
  && !(props.message.risks?.length),
)

const detailOpen = ref(false)
</script>

<template>
  <div class="flex gap-3 group">
    <div
      class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm"
      :class="[cfg.bg, cfg.border]"
    >
      {{ cfg.icon }}
    </div>

    <div class="flex-1 min-w-0">
      <div class="flex flex-wrap items-center gap-2 mb-1.5">
        <span class="text-sm font-semibold" :class="cfg.color">{{ message.title }}</span>
        <span
          class="rounded-full px-2 py-0.5 text-xs font-medium"
          :class="statusColor[message.status] ?? statusColor.pending"
        >
          {{ message.status }}
        </span>
        <span v-if="message.model" class="rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-xs text-zinc-400">
          {{ message.model }}
        </span>
        <span v-if="message.latency_ms !== undefined" class="text-xs text-zinc-600">
          {{ message.latency_ms }}ms
        </span>
        <span v-if="message.token_estimate !== undefined" class="text-xs text-zinc-600">
          ~{{ message.token_estimate }} tok
        </span>
      </div>

      <div
        class="rounded-xl rounded-tl-sm border px-4 py-3 text-sm leading-relaxed"
        :class="[cfg.bg, cfg.border, cfg.color]"
      >
        <div v-if="isLast && isRunning && !mainText && !message.router" class="flex items-center gap-1.5 h-5">
          <span class="h-2 w-2 rounded-full bg-current animate-bounce [animation-delay:0ms]" />
          <span class="h-2 w-2 rounded-full bg-current animate-bounce [animation-delay:150ms]" />
          <span class="h-2 w-2 rounded-full bg-current animate-bounce [animation-delay:300ms]" />
        </div>

        <p v-else-if="mainText && !message.router" class="whitespace-pre-wrap text-zinc-100">
          {{ mainText }}
        </p>
        <p v-else-if="!message.router && !message.risks?.length" class="text-zinc-500 italic text-xs">
          Processing…
        </p>

        <RouterSummaryBlock v-if="message.router" :router="message.router" class="text-zinc-100" />

        <div v-if="message.risks?.length" class="mt-3">
          <RiskItemList :risks="message.risks" compact />
        </div>

        <template v-if="showPlainDetail">
          <button
            type="button"
            class="mt-2 flex items-center gap-1 text-xs opacity-60 hover:opacity-100 transition-opacity"
            @click="detailOpen = !detailOpen"
          >
            <span>{{ detailOpen ? '▾' : '▸' }}</span>
            <span>{{ detailOpen ? 'Hide details' : 'Show details' }}</span>
          </button>
          <p v-if="detailOpen" class="mt-2 whitespace-pre-wrap text-xs opacity-80 leading-relaxed border-t border-current/20 pt-2 text-zinc-300">
            {{ message.message }}
          </p>
        </template>
      </div>

      <p v-if="message.to_agent" class="mt-1 text-xs text-zinc-600 pl-1">
        → handoff to <span class="text-zinc-400">{{ message.to_agent }}</span>
      </p>
    </div>
  </div>
</template>
