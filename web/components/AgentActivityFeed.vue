<script setup lang="ts">
import { humanizeActivitySummary } from '../utils/humanizeOutput'

defineProps<{
  events: Record<string, unknown>[]
  running?: boolean
}>()

const agentIcon: Record<string, string> = {
  orchestrator: '🧠', planner: '📋', executor: '⚡',
  auditor: '🔍', 'security-auditor': '🛡', 'final-reviewer': '✅',
  router: '🔀', memory: '💾', system: '⚙',
}

const agentColor: Record<string, string> = {
  orchestrator: 'text-blue-400',     planner: 'text-blue-400',
  executor: 'text-emerald-400',      auditor: 'text-amber-400',
  'security-auditor': 'text-rose-400', 'final-reviewer': 'text-purple-400',
  router: 'text-cyan-400',           memory: 'text-zinc-400',
  system: 'text-zinc-500',
}

const dotColor: Record<string, string> = {
  orchestrator: 'bg-blue-500',    planner: 'bg-blue-500',
  executor: 'bg-emerald-500',     auditor: 'bg-amber-500',
  'security-auditor': 'bg-rose-500', 'final-reviewer': 'bg-purple-500',
  router: 'bg-cyan-500',          memory: 'bg-zinc-500',
  system: 'bg-zinc-600',
}

function inferAgent(type: string): string {
  if (type.includes('planner') || type.includes('orchestrat')) return 'orchestrator'
  if (type.includes('executor')) return 'executor'
  if (type.includes('security')) return 'security-auditor'
  if (type.includes('auditor')) return 'auditor'
  if (type.includes('final')) return 'final-reviewer'
  if (type.includes('router') || type.includes('routing')) return 'router'
  if (type.includes('memory')) return 'memory'
  return 'system'
}

function label(evt: Record<string, unknown>): string {
  const type = String(evt.type ?? '')
  return type.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function summary(evt: Record<string, unknown>): string {
  const agent = inferAgent(String(evt.type ?? ''))
  const text = humanizeActivitySummary(agent, evt)
  return text.length > 200 ? `${text.slice(0, 200)}…` : text
}

function statusDot(evt: Record<string, unknown>): string {
  const s = String(evt.status ?? '')
  if (s === 'ok' || s === 'success' || s === 'completed') return 'bg-emerald-500'
  if (s === 'fail' || s === 'failed' || s === 'error') return 'bg-rose-500'
  if (s === 'running') return 'bg-blue-500 animate-pulse'
  return 'bg-zinc-600'
}
</script>

<template>
  <div class="space-y-0">
    <div v-if="events.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
      <span class="text-3xl mb-2">🤖</span>
      <p class="text-sm text-zinc-500">Agent activity will appear here.</p>
      <p class="text-xs text-zinc-600 mt-1">Submit a prompt to start.</p>
    </div>

    <div v-else class="relative">
      <!-- Vertical line -->
      <div class="absolute left-[15px] top-3 bottom-3 w-px bg-zinc-800" />

      <div
        v-for="(evt, idx) in events"
        :key="idx"
        class="relative flex gap-3 pb-4"
      >
        <!-- Timeline dot -->
        <div class="relative z-10 mt-1 flex h-8 w-8 shrink-0 items-center justify-center">
          <span
            class="absolute h-2 w-2 rounded-full"
            :class="statusDot(evt)"
          />
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0 pt-0.5">
          <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
            <span class="text-xs" :class="agentColor[inferAgent(String(evt.type ?? ''))] ?? 'text-zinc-400'">
              {{ agentIcon[inferAgent(String(evt.type ?? ''))] ?? '⚙' }}
              <span class="font-medium ml-0.5">{{ inferAgent(String(evt.type ?? '')) }}</span>
            </span>
            <span class="text-xs text-zinc-600">·</span>
            <span class="text-xs text-zinc-400 font-medium">{{ label(evt) }}</span>
          </div>
          <p v-if="summary(evt)" class="text-xs text-zinc-400 leading-relaxed whitespace-pre-wrap">
            {{ summary(evt) }}
          </p>
        </div>
      </div>

      <!-- Live indicator -->
      <div v-if="running" class="relative flex gap-3">
        <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center">
          <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse" />
        </div>
        <div class="flex-1 pt-1.5 flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-zinc-500 animate-bounce [animation-delay:0ms]" />
          <span class="h-1.5 w-1.5 rounded-full bg-zinc-500 animate-bounce [animation-delay:120ms]" />
          <span class="h-1.5 w-1.5 rounded-full bg-zinc-500 animate-bounce [animation-delay:240ms]" />
          <span class="text-xs text-zinc-500 ml-1">Agent working…</span>
        </div>
      </div>
    </div>
  </div>
</template>
