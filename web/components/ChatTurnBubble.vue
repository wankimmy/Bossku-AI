<script setup lang="ts">
import { computed } from 'vue'
import type { ChatTurn } from '~/composables/useLandingChat'
import { renderMarkdown } from '~/utils/renderMarkdown'

const props = defineProps<{
  turn: ChatTurn
}>()

const renderedContent = computed(() =>
  props.turn.role === 'assistant' ? renderMarkdown(props.turn.content) : null,
)
</script>

<template>
  <div class="flex gap-3">
    <div
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium"
      :class="turn.role === 'user' ? 'bg-emerald-800 text-white' : 'bg-zinc-700 text-zinc-200'"
      aria-hidden="true"
    >
      {{ turn.role === 'user' ? 'You' : 'AI' }}
    </div>
    <div
      class="max-w-[min(100%,42rem)] rounded-lg px-3 py-2.5 text-sm leading-relaxed"
      :class="turn.role === 'user'
        ? 'bg-emerald-900/50 text-emerald-50 border border-emerald-800/40'
        : 'bg-zinc-800/80 text-zinc-100 border border-zinc-700/50'"
    >
      <!-- AI messages: rendered markdown -->
      <div
        v-if="renderedContent"
        class="chat-md"
        v-html="renderedContent"
      />
      <!-- User messages: plain pre-wrap -->
      <p v-else class="whitespace-pre-wrap">
        {{ turn.content }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.chat-md :deep(.md-h2) {
  font-size: 0.9rem;
  font-weight: 700;
  color: #f4f4f5;
  margin: 0.75rem 0 0.2rem;
  padding-bottom: 0.2rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  letter-spacing: 0.01em;
}
.chat-md :deep(.md-h3) {
  font-size: 0.85rem;
  font-weight: 700;
  color: #e4e4e7;
  margin: 0.65rem 0 0.2rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.chat-md :deep(.md-p) {
  margin: 0.25rem 0;
  color: #d4d4d8;
  line-height: 1.6;
}
.chat-md :deep(.md-ul) {
  margin: 0.2rem 0 0.4rem 0;
  padding-left: 1.25rem;
  list-style: disc;
}
.chat-md :deep(.md-ul li) {
  margin: 0.15rem 0;
  color: #d4d4d8;
  line-height: 1.5;
}
.chat-md :deep(.md-spacer) {
  height: 0.35rem;
}
.chat-md :deep(.inline-code) {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 3px;
  padding: 0.05em 0.35em;
  font-family: ui-monospace, monospace;
  font-size: 0.8em;
  color: #93c5fd;
}
.chat-md :deep(strong) {
  font-weight: 700;
  color: #f4f4f5;
}
/* First element: no top margin */
.chat-md :deep(.md-h2:first-child),
.chat-md :deep(.md-h3:first-child),
.chat-md :deep(.md-p:first-child) {
  margin-top: 0;
}
</style>
