<script setup lang="ts">
import type { BrainData, LearningEvent, SkillCandidate } from '~/types/api'
import { normalizeBrainData } from '~/composables/useBrainStats'

definePageMeta({ layout: 'default' })

type Tab = 'overview' | 'memory' | 'learning' | 'candidates' | 'feedback' | 'conflicts'
const activeTab = ref<Tab>('overview')

const { data: rawBrain } = await useFetch<Record<string, unknown>>(apiUrl('/brain'), { server: false })
const brainData = computed(() => normalizeBrainData(rawBrain.value as Parameters<typeof normalizeBrainData>[0]))

const { data: learningData, refresh: refreshLearning } = await useFetch<{ data: LearningEvent[] }>(
  apiUrl('/learning?status=pending'),
  { server: false, immediate: false },
)
const learningEvents = computed(() => learningData.value?.data ?? [])

const { data: candidatesData, refresh: refreshCandidates } = await useFetch<{ data: SkillCandidate[] }>(
  apiUrl('/skill-candidates?status=pending'),
  { server: false, immediate: false },
)
const candidates = computed(() => candidatesData.value?.data ?? [])

const { data: graphData, pending: graphPending, refresh: refreshGraph } = await useFetch(
  apiUrl('/knowledge-graph'),
  { server: false, immediate: false },
)
const graphFetched = ref(false)

const conflictNodes = computed(() => (graphData.value?.nodes ?? []).filter((n: { has_conflict?: boolean }) => n.has_conflict))
const conflictEdges = computed(() => (graphData.value?.edges ?? []).filter((e: { is_conflict?: boolean }) => e.is_conflict))

function removeEvent(id: string) {
  if (learningData.value?.data) {
    learningData.value.data = learningData.value.data.filter(e => e.id !== id)
  }
}

function removeCandidate(id: string) {
  if (candidatesData.value?.data) {
    candidatesData.value.data = candidatesData.value.data.filter(c => c.id !== id)
  }
}

async function switchTab(tab: Tab) {
  activeTab.value = tab
  if (tab === 'learning' && !learningData.value) await refreshLearning()
  if (tab === 'candidates' && !candidatesData.value) await refreshCandidates()
  if (tab === 'conflicts' && !graphFetched.value) {
    graphFetched.value = true
    await refreshGraph()
  }
}

const tabs: { key: Tab; label: string; icon: string }[] = [
  { key: 'overview', label: 'Overview', icon: '📊' },
  { key: 'memory', label: 'Memory network', icon: '🧠' },
  { key: 'learning', label: 'Learning inbox', icon: '📥' },
  { key: 'candidates', label: 'Skill candidates', icon: '✨' },
  { key: 'feedback', label: 'Feedback', icon: '💬' },
  { key: 'conflicts', label: 'Conflicts', icon: '⚠' },
]
</script>

<template>
  <div class="space-y-6">
    <BrainHero :stats="brainData" />

    <nav class="flex flex-wrap gap-1 rounded-xl border border-zinc-800 bg-zinc-900/50 p-1">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="rounded-lg px-4 py-2 text-sm font-medium transition"
        :class="activeTab === tab.key
          ? tab.key === 'memory'
            ? 'bg-violet-900/60 text-violet-100'
            : 'bg-zinc-800 text-white'
          : 'text-zinc-400 hover:text-zinc-200'"
        @click="switchTab(tab.key)"
      >
        <span class="mr-1.5">{{ tab.icon }}</span>{{ tab.label }}
      </button>
    </nav>

    <div v-if="activeTab === 'overview'" class="space-y-6">
      <BrainOverview :brain-data="brainData" />
      <div class="flex flex-wrap gap-3">
        <NuxtLink
          to="/knowledge-graph"
          class="rounded-lg border border-indigo-800/50 bg-indigo-950/30 px-4 py-3 text-sm text-indigo-200 hover:bg-indigo-900/40"
        >
          Open full knowledge graph →
        </NuxtLink>
        <NuxtLink
          to="/skills-graph"
          class="rounded-lg border border-emerald-800/50 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-200 hover:bg-emerald-900/40"
        >
          Open skills graph →
        </NuxtLink>
      </div>
    </div>

    <div v-else-if="activeTab === 'memory'">
      <BrainMemoryGraph />
    </div>

    <div v-else-if="activeTab === 'learning'">
      <BrainLearningInbox
        :events="learningEvents"
        @accept="removeEvent"
        @reject="removeEvent"
      />
    </div>

    <div v-else-if="activeTab === 'candidates'">
      <BrainSkillCandidateList
        :candidates="candidates"
        @approve="removeCandidate"
        @reject="removeCandidate"
      />
    </div>

    <div v-else-if="activeTab === 'feedback'">
      <NuxtLink to="/feedback" class="text-sm text-emerald-400 hover:underline">
        → View full feedback on the Feedback page
      </NuxtLink>
    </div>

    <div v-else-if="activeTab === 'conflicts'" class="space-y-6">
      <UiSkeleton v-if="graphPending" class="h-40 w-full" />
      <template v-else>
        <section class="space-y-3">
          <h2 class="text-sm font-semibold text-zinc-300">
            Conflicting nodes
            <span class="ml-1.5 rounded-full bg-red-900/50 px-2 py-0.5 text-xs text-red-400">{{ conflictNodes.length }}</span>
          </h2>
          <UiEmptyState
            v-if="conflictNodes.length === 0"
            title="No conflicting nodes."
            hint="All knowledge nodes are consistent."
          />
          <ul v-else class="divide-y divide-zinc-800 rounded-xl border border-zinc-800">
            <li
              v-for="node in conflictNodes"
              :key="node.id"
              class="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
            >
              <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-white">{{ node.label }}</span>
                  <span class="rounded-full bg-red-900/50 px-2 py-0.5 text-xs text-red-400">conflict</span>
                </div>
              </div>
              <MemoryConfidencePill v-if="node.confidence !== undefined" :confidence="node.confidence" />
            </li>
          </ul>
        </section>
      </template>
    </div>
  </div>
</template>
