<script setup lang="ts">
import { computed, ref } from 'vue'
import type { AgentMessage } from '../types/bossku'
import { themeForAgent } from '../utils/agentTheme'
import { renderMarkdown } from '~/utils/renderMarkdown'

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

const renderedMainText = computed(() =>
  mainText.value ? renderMarkdown(mainText.value) : '',
)

const showPlainDetail = computed(() =>
  props.message.message
  && !props.message.router
  && !(props.message.risks?.length),
)

const detailOpen = ref(false)

const evaluation = computed(() => {
  const arts = props.message.artifacts
  if (!arts || typeof arts !== 'object') return null
  const data = (arts as Record<string, unknown>).evaluation
  if (!data || typeof data !== 'object') return null
  return data as Record<string, unknown>
})

const evaluationScore = computed(() => {
  const value = evaluation.value?.score
  const score = typeof value === 'number' ? value : Number(value ?? NaN)
  return Number.isFinite(score) ? score : null
})

const evaluationProofSummary = computed(() => {
  const data = evaluation.value?.proof_summary
  if (!data || typeof data !== 'object') return []
  const record = data as Record<string, unknown>
  return [
    ['files read', record.files_read],
    ['files changed', record.files_changed],
    ['tests run', record.tests_run],
    ['audit findings', record.audit_findings],
    ['security pass', record.security_pass],
  ]
    .filter(([, value]) => value !== undefined && value !== null)
    .map(([label, value]) => `${label}: ${value}`)
})

const proofFiles = computed((): string[] => {
  const arts = props.message.artifacts
  if (!arts || typeof arts !== 'object') return []
  const fromProof = Array.isArray((arts as Record<string, unknown>).proof_files)
    ? ((arts as Record<string, unknown>).proof_files as string[])
    : []
  if (fromProof.length) return fromProof
  const paths = new Set<string>()
  for (const key of ['files_read', 'files_changed'] as const) {
    const items = (arts as Record<string, unknown>)[key]
    if (!Array.isArray(items)) continue
    for (const item of items) {
      if (typeof item === 'string') paths.add(item)
      else if (item && typeof item === 'object' && typeof (item as Record<string, unknown>).path === 'string') {
        paths.add((item as Record<string, unknown>).path as string)
      }
    }
  }
  return [...paths]
})

const needsInput = computed(() =>
  Boolean((props.message.artifacts as Record<string, unknown> | undefined)?.needs_user_input),
)

const blockers = computed((): string[] => {
  const b = (props.message.artifacts as Record<string, unknown> | undefined)?.blockers
  return Array.isArray(b) ? b.map(String).filter(Boolean) : []
})
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
        <span
          v-if="message.from_agent"
          class="rounded-full border border-zinc-700 bg-zinc-900/70 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400"
        >
          from {{ message.from_agent }}
        </span>
        <span
          v-if="message.to_agent"
          class="rounded-full border border-zinc-700 bg-zinc-900/70 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400"
        >
          to {{ message.to_agent }}
        </span>
        <span
          v-if="evaluationScore !== null"
          class="rounded-full border border-fuchsia-500/50 bg-fuchsia-950/40 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-200"
        >
          eval {{ evaluationScore.toFixed(2) }}
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

        <div v-else-if="mainText && !message.router" class="agent-md" v-html="renderedMainText" />
        <p v-else-if="!message.router && !message.risks?.length" class="text-zinc-500 italic text-xs">
          Processing…
        </p>

        <div
          v-if="needsInput"
          class="mb-2 rounded-lg border border-amber-500/40 bg-amber-950/30 px-3 py-2 text-xs text-amber-200"
        >
          <span class="font-medium">Needs your input</span>
          <span v-if="message.from_agent"> from {{ message.from_agent }}</span>
          <ul v-if="blockers.length" class="mt-1 list-disc pl-4 text-amber-200/90">
            <li v-for="(b, i) in blockers" :key="i">{{ b }}</li>
          </ul>
        </div>

        <div v-if="proofFiles.length" class="mb-2">
          <p class="text-xs text-zinc-500 mb-1">Proof (files)</p>
          <div class="flex flex-wrap gap-1">
            <span
              v-for="path in proofFiles.slice(0, 12)"
              :key="path"
              class="rounded bg-zinc-800/80 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400"
            >{{ path }}</span>
          </div>
        </div>

        <div
          v-if="evaluation"
          class="mb-2 rounded-lg border border-fuchsia-500/30 bg-fuchsia-950/20 px-3 py-2 text-xs text-fuchsia-100"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium">Post-memory eval</span>
            <span
              v-if="evaluation.verdict"
              class="rounded-full border border-fuchsia-400/40 bg-fuchsia-900/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-200"
            >
              {{ evaluation.verdict }}
            </span>
            <span v-if="evaluationScore !== null" class="text-fuchsia-200/90">
              score {{ evaluationScore.toFixed(2) }}
            </span>
          </div>
          <p v-if="evaluation.summary" class="mt-1 text-fuchsia-50/90">
            {{ evaluation.summary }}
          </p>
          <p v-if="evaluation.recommendation" class="mt-1 text-fuchsia-200/90">
            {{ evaluation.recommendation }}
          </p>
          <div v-if="evaluationProofSummary.length" class="mt-2 flex flex-wrap gap-1">
            <span
              v-for="(item, i) in evaluationProofSummary"
              :key="i"
              class="rounded bg-fuchsia-900/40 px-1.5 py-0.5 font-mono text-[10px] text-fuchsia-200"
            >
              {{ item }}
            </span>
          </div>
        </div>

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

<style scoped>
.agent-md :deep(.md-h2) {
  font-size: 0.8rem;
  font-weight: 700;
  color: #f4f4f5;
  margin: 0.6rem 0 0.15rem;
  padding-bottom: 0.15rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.agent-md :deep(.md-h3) {
  font-size: 0.78rem;
  font-weight: 700;
  color: #e4e4e7;
  margin: 0.5rem 0 0.1rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.agent-md :deep(.md-p) {
  margin: 0.2rem 0;
  color: #d4d4d8;
  line-height: 1.55;
  font-size: 0.85rem;
}
.agent-md :deep(.md-ul) {
  margin: 0.15rem 0 0.3rem 0;
  padding-left: 1.1rem;
  list-style: disc;
}
.agent-md :deep(.md-ul li) {
  margin: 0.1rem 0;
  color: #d4d4d8;
  line-height: 1.45;
  font-size: 0.85rem;
}
.agent-md :deep(.md-spacer) {
  height: 0.25rem;
}
.agent-md :deep(.inline-code) {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 3px;
  padding: 0.05em 0.3em;
  font-family: ui-monospace, monospace;
  font-size: 0.8em;
  color: #93c5fd;
}
.agent-md :deep(strong) {
  font-weight: 700;
  color: #f4f4f5;
}
.agent-md :deep(.md-h2:first-child),
.agent-md :deep(.md-h3:first-child),
.agent-md :deep(.md-p:first-child) {
  margin-top: 0;
}
</style>
