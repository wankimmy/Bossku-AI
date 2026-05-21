<script setup lang="ts">
import type { BrainData, LearningEvent, SkillCandidate, KnowledgeGraphResponse } from '~/types/api'

definePageMeta({ layout: 'default' })

const base = useApiBase()

type Tab = 'overview' | 'learning' | 'candidates' | 'feedback' | 'conflicts'
const activeTab = ref<Tab>('overview')

// ── Overview ───────────────────────────────────────────────────────────────
const { data: brainData } = useFetch<BrainData>(`${base}/api/brain`, { server: false })

// ── Learning inbox ────────────────────────────────────────────────────────
const { data: learningData, refresh: refreshLearning } = useFetch<{ data: LearningEvent[] }>(
  `${base}/api/learning?status=pending`,
  { server: false, immediate: false },
)
const learningEvents = computed(() => learningData.value?.data ?? [])

function removeEvent(id: string) {
  if (learningData.value?.data) {
    learningData.value.data = learningData.value.data.filter(e => e.id !== id)
  }
}

// ── Skill candidates ──────────────────────────────────────────────────────
const { data: candidatesData, refresh: refreshCandidates } = useFetch<{ data: SkillCandidate[] }>(
  `${base}/api/skill-candidates?status=pending`,
  { server: false, immediate: false },
)
const candidates = computed(() => candidatesData.value?.data ?? [])

function removeCandidate(id: string) {
  if (candidatesData.value?.data) {
    candidatesData.value.data = candidatesData.value.data.filter(c => c.id !== id)
  }
}

// ── Conflicts ─────────────────────────────────────────────────────────────
const { data: graphData, pending: graphPending, refresh: refreshGraph } = useFetch<KnowledgeGraphResponse>(
  `${base}/api/knowledge-graph`,
  { server: false, immediate: false },
)
const graphFetched = ref(false)

const conflictNodes = computed(() => (graphData.value?.nodes ?? []).filter(n => n.has_conflict === true))
const conflictEdges = computed(() => (graphData.value?.edges ?? []).filter(e => e.is_conflict === true))

// ── Tab switching ─────────────────────────────────────────────────────────
async function switchTab(tab: Tab) {
  activeTab.value = tab
  if (tab === 'learning' && !learningData.value) await refreshLearning()
  if (tab === 'candidates' && !candidatesData.value) await refreshCandidates()
  if (tab === 'conflicts' && !graphFetched.value) {
    graphFetched.value = true
    await refreshGraph()
  }
}

const tabs: { key: Tab; label: string }[] = [
  { key: 'overview', label: 'Overview' },
  { key: 'learning', label: 'Learning Inbox' },
  { key: 'candidates', label: 'Skill Candidates' },
  { key: 'feedback', label: 'Feedback' },
  { key: 'conflicts', label: 'Conflicts' },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold">Brain</h1>
      <p class="text-sm text-zinc-400">Learning pipeline, skill candidates, and knowledge conflicts.</p>
    </div>

    <!-- Tab bar -->
    <nav class="flex gap-1 rounded-xl border border-zinc-800 bg-zinc-900/50 p-1">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :class="activeTab === tab.key
          ? 'bg-zinc-800 text-white'
          : 'text-zinc-400 hover:text-zinc-200'"
        class="rounded-lg px-4 py-2 text-sm font-medium transition"
        @click="switchTab(tab.key)"
      >
        {{ tab.label }}
      </button>
    </nav>

    <!-- Overview -->
    <div v-if="activeTab === 'overview'">
      <BrainBrainOverview :brain-data="(brainData as BrainData | null) ?? null" />
    </div>

    <!-- Learning Inbox -->
    <div v-else-if="activeTab === 'learning'">
      <BrainLearningInbox
        :events="learningEvents"
        @accept="removeEvent"
        @reject="removeEvent"
      />
    </div>

    <!-- Skill Candidates -->
    <div v-else-if="activeTab === 'candidates'">
      <BrainSkillCandidateList
        :candidates="candidates"
        @approve="removeCandidate"
        @reject="removeCandidate"
      />
    </div>

    <!-- Feedback -->
    <div v-else-if="activeTab === 'feedback'">
      <NuxtLink to="/feedback" class="text-sm text-emerald-400 hover:underline">
        → View full feedback in Feedback page
      </NuxtLink>
    </div>

    <!-- Conflicts -->
    <div v-else-if="activeTab === 'conflicts'" class="space-y-6">
      <UiSkeleton v-if="graphPending" class="h-40 w-full" />

      <template v-else>
        <!-- Conflict nodes -->
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
                  <span
                    class="rounded border px-2 py-0.5 text-xs capitalize"
                    :class="{
                      'border-emerald-700 text-emerald-400': node.type === 'skill',
                      'border-blue-700 text-blue-400': node.type === 'run',
                      'border-purple-700 text-purple-400': node.type === 'memory',
                      'border-zinc-700 text-zinc-400': !['skill','run','memory'].includes(node.type ?? ''),
                    }"
                  >{{ node.type ?? 'unknown' }}</span>
                  <span class="rounded-full bg-red-900/50 px-2 py-0.5 text-xs text-red-400">conflict</span>
                </div>
                <p v-if="node.properties?.reason" class="text-xs text-zinc-400">
                  {{ node.properties.reason }}
                </p>
                <p v-else-if="node.metadata?.reason" class="text-xs text-zinc-400">
                  {{ node.metadata.reason }}
                </p>
              </div>
              <MemoryConfidencePill v-if="node.confidence !== undefined" :confidence="node.confidence" />
            </li>
          </ul>
        </section>

        <!-- Conflict edges -->
        <section class="space-y-3">
          <h2 class="text-sm font-semibold text-zinc-300">
            Conflicting relationships
            <span class="ml-1.5 rounded-full bg-red-900/50 px-2 py-0.5 text-xs text-red-400">{{ conflictEdges.length }}</span>
          </h2>

          <UiEmptyState
            v-if="conflictEdges.length === 0"
            title="No conflicting relationships."
            hint="All knowledge edges are consistent."
          />

          <ul v-else class="divide-y divide-zinc-800 rounded-xl border border-zinc-800">
            <li
              v-for="edge in conflictEdges"
              :key="edge.id"
              class="flex flex-wrap items-center gap-2 px-4 py-3 text-sm"
            >
              <span class="font-mono text-xs text-zinc-400">{{ edge.source_id }}</span>
              <span class="text-zinc-500">→</span>
              <span class="font-mono text-xs text-zinc-400">{{ edge.target_id }}</span>
              <span v-if="edge.relation" class="rounded border border-red-800 px-2 py-0.5 text-xs text-red-400">{{ edge.relation }}</span>
            </li>
          </ul>
        </section>
      </template>
    </div>
  </div>
</template>
