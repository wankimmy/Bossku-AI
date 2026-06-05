<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const router = useRouter()

type PageTab = 'inspector' | 'brain'

const pageTab = computed<PageTab>(() =>
  route.query.tab === 'brain' ? 'brain' : 'inspector',
)

function setPageTab(tab: PageTab) {
  router.replace({ path: '/memory', query: tab === 'inspector' ? {} : { tab } })
}

const pageTabs: { key: PageTab; label: string }[] = [
  { key: 'inspector', label: 'Memory inspector' },
  { key: 'brain', label: 'Brain' },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold text-zinc-100">
        Memory & Brain
      </h1>
      <p class="mt-1 text-sm text-zinc-500">
        Search stored memory and monitor learning, candidates, and conflicts.
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

    <MemoryInspectorPanel v-if="pageTab === 'inspector'" />
    <BrainDashboard v-else />
  </div>
</template>
