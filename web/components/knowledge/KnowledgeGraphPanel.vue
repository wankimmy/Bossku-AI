<script setup lang="ts">
import type { WorkspaceGraphResponse } from '~/types/api'

const api = useApi()

const { data: graphData, pending, refresh } = await useFetch<WorkspaceGraphResponse>(
  apiUrl('/knowledge-graph'),
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
  <div class="min-h-[calc(100vh-14rem)]">
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
  </div>
</template>
