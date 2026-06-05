<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const router = useRouter()

type PageTab = 'library' | 'graph'

const pageTab = computed<PageTab>(() =>
  route.query.tab === 'graph' ? 'graph' : 'library',
)

function setPageTab(tab: PageTab) {
  router.replace({ path: '/knowledge', query: tab === 'library' ? {} : { tab } })
}

const pageTabs: { key: PageTab; label: string }[] = [
  { key: 'library', label: 'Library' },
  { key: 'graph', label: 'Knowledge graph' },
]
</script>

<template>
  <div
    class="mx-auto flex min-w-0 w-full flex-col gap-6 overflow-hidden"
    :class="pageTab === 'library' ? 'max-w-7xl' : ''"
  >
    <section
      v-if="pageTab === 'library'"
      class="min-w-0 rounded-3xl border border-emerald-400/20 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.18),transparent_32%),linear-gradient(135deg,#0f172a,#09090b_58%,#111827)] p-5 shadow-2xl shadow-black/30 sm:p-6"
    >
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-300">BosskuAI memory intake</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-50">Knowledge</h1>
        <p class="mt-2 max-w-2xl text-sm text-zinc-400">
          Import URLs and tool memory, or explore how knowledge connects in the graph.
        </p>
      </div>
    </section>

    <div v-else class="space-y-1">
      <h1 class="text-xl font-semibold text-zinc-100">
        Knowledge
      </h1>
      <p class="text-sm text-zinc-500">
        Interactive graph of skills, runs, and memories.
      </p>
    </div>

    <nav class="flex flex-wrap gap-1 rounded-lg bg-zinc-900 p-1">
      <button
        v-for="tab in pageTabs"
        :key="tab.key"
        type="button"
        class="rounded-md px-4 py-2 text-sm font-medium"
        :class="pageTab === tab.key ? 'bg-zinc-800 text-zinc-100 shadow' : 'text-zinc-500 hover:text-zinc-300'"
        @click="setPageTab(tab.key)"
      >
        {{ tab.label }}
      </button>
    </nav>

    <KnowledgeLibraryPanel v-if="pageTab === 'library'" />
    <KnowledgeGraphPanel v-else />
  </div>
</template>
