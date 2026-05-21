<script setup lang="ts">
import type { AgentMessage } from '~/types/api'

defineProps<{ message: AgentMessage }>()

const reasoningExpanded = ref(false)
</script>

<template>
  <article class="rounded-lg border-l-2 border-l-emerald-500 border border-zinc-700 bg-zinc-800 p-4">
    <!-- [BOSSKUAI] header block -->
    <div class="mb-3 rounded bg-zinc-900 border border-zinc-700 px-3 py-2">
      <div class="text-xs font-bold text-emerald-400 mb-1.5 tracking-widest">[BOSSKUAI]</div>
      <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-400 font-mono">
        <span v-if="message.skill">Skill: <span class="text-zinc-200">{{ message.skill }}</span></span>
        <span>Agent: <span class="text-zinc-200">{{ message.agent }}</span></span>
        <span v-if="message.model_role">Model Role: <span class="text-zinc-200">{{ message.model_role }}</span></span>
        <span>Memory Used: <span :class="message.memory_used ? 'text-emerald-400' : 'text-zinc-500'">{{ message.memory_used ? 'yes' : 'no' }}</span></span>
        <span v-if="message.model">Model: <span class="text-zinc-200">{{ message.model }}</span></span>
      </div>
    </div>

    <!-- Reasoning section -->
    <div v-if="message.safe_reasoning_summary" class="mb-3">
      <button
        type="button"
        class="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-300 transition-colors"
        @click="reasoningExpanded = !reasoningExpanded"
      >
        <span>{{ reasoningExpanded ? '▼' : '▶' }}</span>
        <span>Reasoning</span>
        <span
          class="ml-1 text-zinc-600 cursor-help"
          title="BosskuAI shows safe reasoning summaries, not hidden private chain-of-thought."
        >ℹ</span>
      </button>
      <div
        v-if="reasoningExpanded"
        class="mt-2 rounded bg-zinc-900/50 border border-zinc-700/50 px-3 py-2 text-xs text-zinc-400 leading-relaxed"
      >
        {{ message.safe_reasoning_summary }}
      </div>
    </div>

    <!-- Title and status -->
    <div class="flex items-start justify-between gap-3 mb-2">
      <h2 class="text-sm font-semibold text-zinc-100">{{ message.title }}</h2>
      <span class="shrink-0 rounded border border-zinc-600 px-2 py-0.5 font-mono text-xs text-zinc-400">
        {{ message.status }}
      </span>
    </div>

    <!-- Main content -->
    <pre
      v-if="message.content"
      class="text-sm text-zinc-300 leading-relaxed whitespace-pre-wrap break-words font-sans"
    >{{ message.content }}</pre>
    <p
      v-else-if="message.summary"
      class="text-sm text-zinc-300 leading-relaxed"
    >{{ message.summary }}</p>
    <p
      v-if="message.message && message.message !== message.summary"
      class="mt-2 whitespace-pre-wrap text-sm text-zinc-400 leading-relaxed"
    >{{ message.message }}</p>

    <!-- Footer metadata -->
    <div
      v-if="message.to_agent || message.latency_ms != null || message.token_estimate != null"
      class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500"
    >
      <span v-if="message.to_agent">→ {{ message.to_agent }}</span>
      <span v-if="message.latency_ms != null">{{ message.latency_ms }}ms</span>
      <span v-if="message.token_estimate != null">~{{ message.token_estimate }} tokens</span>
    </div>
  </article>
</template>
