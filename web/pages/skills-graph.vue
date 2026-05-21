<script setup lang="ts">
import type { WorkspaceGraphResponse } from '~/types/api'

definePageMeta({ layout: 'graph' })

const base = useApiBase()
const api = useApi()
const toast = useToast()
const bootstrapLoading = ref(false)

const { data: graphData, pending, refresh } = await useFetch<WorkspaceGraphResponse>(
  `${base}/api/workspace/graph`,
  { server: false },
)

async function installBosskuSkills() {
  bootstrapLoading.value = true
  try {
    const res = await api.post<{
      message: string
      project_name: string
      copied: string[]
    }>('/project/skills/bootstrap')
    toast.success(res.message || 'BosskuAI skills installed.')
    await refresh()
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : String(e))
  }
  finally {
    bootstrapLoading.value = false
  }
}
</script>

<template>
  <GraphWorkspaceShell
    title="Skills Graph"
    description="Force-directed map of BosskuAI skills from skill-index.json — cross-references, trigger overlaps, depth, and playbooks."
    variant="skills"
    :data="graphData ?? undefined"
    :pending="pending"
    :bootstrap-loading="bootstrapLoading"
    @rebuild="refresh()"
    @bootstrap-skills="installBosskuSkills"
  />
</template>
