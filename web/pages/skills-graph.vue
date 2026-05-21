<script setup lang="ts">
import type { WorkspaceGraphResponse } from '~/types/api'

definePageMeta({ layout: 'graph' })

const base = useApiBase()

const { data: graphData, pending, refresh } = await useFetch<WorkspaceGraphResponse>(
  `${base}/api/workspace/graph`,
  { server: false },
)
</script>

<template>
  <GraphWorkspaceShell
    title="Skills Graph"
    description="Force-directed map of BosskuAI skills from skill-index.json — cross-references, trigger overlaps, depth, and playbooks."
    variant="skills"
    :data="graphData ?? undefined"
    :pending="pending"
    @rebuild="refresh()"
  />
</template>
