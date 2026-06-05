<script setup lang="ts">
import type { Agent, SpecialistAgent } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const { data, pending } = await useAsyncData<Agent[]>('agents', () => api.get('/agents'))
const { data: specialistData, pending: specialistsPending, refresh: refreshSpecialists } = await useAsyncData<{ data?: SpecialistAgent[] } | SpecialistAgent[]>(
  'specialist-agents',
  () => api.get('/specialist-agents'),
)

const agents = computed<Agent[]>(() => {
  const d = data.value
  if (!d) return []
  return Array.isArray(d) ? d : (d as { data?: Agent[] }).data ?? []
})

const specialists = computed<SpecialistAgent[]>(() => {
  const d = specialistData.value
  if (!d) return []
  return Array.isArray(d) ? d : d.data ?? []
})

async function reviewSpecialist(agent: SpecialistAgent, action: 'approve' | 'reject' | 'archive') {
  await api.post(`/specialist-agents/${agent.id}/${action}`)
  await refreshSpecialists()
}

function statusClass(status: string) {
  if (status === 'approved') return 'border-emerald-700/70 bg-emerald-950/50 text-emerald-300'
  if (status === 'draft' || status === 'pending_review') return 'border-amber-700/70 bg-amber-950/40 text-amber-300'
  if (status === 'archived') return 'border-zinc-700 bg-zinc-900 text-zinc-400'
  return 'border-red-800/70 bg-red-950/40 text-red-300'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-zinc-100">Agents</h1>
      <NuxtLink
        to="/personas"
        class="text-sm text-emerald-400 hover:text-emerald-300 underline"
      >
        Edit personas →
      </NuxtLink>
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading...</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="agent in agents"
        :key="agent.role"
        class="rounded-lg bg-zinc-900 border border-zinc-800 p-4"
      >
        <h3 class="text-sm font-semibold text-zinc-100 font-mono">{{ agent.role }}</h3>
        <div class="mt-3 space-y-1.5">
          <div class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Run count</span>
            <span class="text-zinc-300">{{ agent.run_count ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Avg latency</span>
            <span
              class="inline-flex items-center px-1.5 py-0.5 rounded text-xs border border-zinc-700 bg-zinc-800 text-zinc-300"
            >
              {{ agent.avg_latency_ms != null ? agent.avg_latency_ms + 'ms' : '—' }}
            </span>
          </div>
          <div v-if="agent.success_rate != null" class="flex items-center justify-between text-xs">
            <span class="text-zinc-500">Success rate</span>
            <span class="text-emerald-400">{{ (agent.success_rate * 100).toFixed(1) }}%</span>
          </div>
        </div>
      </div>
      <div v-if="agents.length === 0" class="col-span-full text-sm text-zinc-500 text-center py-8">
        No agent data yet.
      </div>
    </div>

    <section class="space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-zinc-100">Specialists</h2>
          <p class="text-xs text-zinc-500">Project-scoped draft and approved agents reused by the orchestrator.</p>
        </div>
      </div>

      <div v-if="specialistsPending" class="text-sm text-zinc-500">Loading specialists...</div>
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <article
          v-for="specialist in specialists"
          :key="specialist.id"
          class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate text-sm font-semibold text-zinc-100">{{ specialist.display_name }}</h3>
              <p class="mt-1 text-xs font-mono text-zinc-500">{{ specialist.role_slug }}</p>
            </div>
            <span
              class="rounded border px-2 py-0.5 text-[11px] uppercase tracking-wide"
              :class="statusClass(specialist.approval_status)"
            >
              {{ specialist.approval_status }}
            </span>
          </div>

          <p v-if="specialist.description" class="mt-3 line-clamp-2 text-sm text-zinc-300">
            {{ specialist.description }}
          </p>

          <div class="mt-4 grid gap-2 text-xs text-zinc-400 sm:grid-cols-2">
            <div>
              <span class="text-zinc-600">Project</span>
              <div class="text-zinc-300">{{ specialist.project?.name ?? specialist.project_id }}</div>
            </div>
            <div>
              <span class="text-zinc-600">Linked skill</span>
              <div class="text-zinc-300">{{ specialist.linked_skill?.name ?? 'None' }}</div>
            </div>
            <div>
              <span class="text-zinc-600">Usage</span>
              <div class="text-zinc-300">{{ specialist.usage_count ?? 0 }} run(s)</div>
            </div>
            <div>
              <span class="text-zinc-600">Pixel</span>
              <div class="text-zinc-300">Palette {{ specialist.pixel_palette ?? 0 }}, hue {{ specialist.pixel_hue_shift ?? 0 }}</div>
            </div>
          </div>

          <div v-if="specialist.trigger_keywords?.length" class="mt-4 flex flex-wrap gap-1.5">
            <span
              v-for="keyword in specialist.trigger_keywords"
              :key="keyword"
              class="rounded border border-zinc-700 bg-zinc-950 px-1.5 py-0.5 text-[11px] text-zinc-300"
            >
              {{ keyword }}
            </span>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-if="specialist.approval_status !== 'approved'"
              type="button"
              class="rounded-md border border-emerald-700/70 px-2 py-1 text-xs text-emerald-300 hover:bg-emerald-950"
              @click="reviewSpecialist(specialist, 'approve')"
            >
              Approve
            </button>
            <button
              v-if="!['rejected', 'archived'].includes(specialist.approval_status)"
              type="button"
              class="rounded-md border border-red-800/70 px-2 py-1 text-xs text-red-300 hover:bg-red-950"
              @click="reviewSpecialist(specialist, 'reject')"
            >
              Reject
            </button>
            <button
              v-if="specialist.approval_status !== 'archived'"
              type="button"
              class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800"
              @click="reviewSpecialist(specialist, 'archive')"
            >
              Archive
            </button>
          </div>
        </article>
        <div v-if="specialists.length === 0" class="text-sm text-zinc-500">
          No specialist agents drafted yet.
        </div>
      </div>
    </section>
  </div>
</template>
