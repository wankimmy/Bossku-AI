<script setup lang="ts">
import type { WorkspaceGraphResponse } from '~/types/api'

definePageMeta({ layout: 'graph' })

const api = useApi()
const base = useApiBase()

const { data: graphData, pending, refresh } = await useFetch<WorkspaceGraphResponse>(
  `${base}/api/knowledge-graph`,
  { server: false },
)

const rebuilding = ref(false)

async function rebuildGraph() {
  rebuilding.value = true
  try {
    await api.post('/knowledge-graph/rebuild')
    await refresh()
  }
  finally {
    rebuilding.value = false
  }
}
</script>

<template>
  <GraphWorkspaceShell
    title="Knowledge Graph"
    description="Skills, runs, and memories from the rebuilt knowledge graph — filter by relation type and color by category or depth."
    variant="knowledge"
    :data="graphData ?? undefined"
    :pending="pending"
    show-rebuild
    :rebuild-label="rebuilding ? 'Rebuilding…' : 'Rebuild graph'"
    @rebuild="rebuildGraph"
  />
</template>
