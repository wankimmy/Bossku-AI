<script setup lang="ts">
import type { CompanyTeam, WorkIssue } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const toast = useToast()

const { data, pending, refresh } = await useAsyncData('work-issues', async () => {
  const response = await api.get('/work-issues') as { data?: WorkIssue[] } | WorkIssue[]
  if (Array.isArray(response)) return response
  return response.data ?? []
})

const issues = computed(() => data.value ?? [])

const { data: teamsData } = await useAsyncData('company-teams', async () => {
  const response = await api.get('/company-teams') as { data?: CompanyTeam[] }
  return response.data ?? []
})

async function updateIssueStatus(id: string, status: WorkIssue['status']) {
  await api.patch(`/work-issues/${id}`, { status })
  await refresh()
}

async function dispatchWakeups() {
  const result = await api.post('/agent-wakeups/dispatch') as { processed?: number; skipped?: number; failed?: number }
  toast.info(`Wakeups processed ${result.processed ?? 0}, skipped ${result.skipped ?? 0}, failed ${result.failed ?? 0}`)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-zinc-100">
          Work issues
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
          Durable tasks from approved plans with assignees and wakeup status.
        </p>
      </div>
      <button
        type="button"
        class="rounded-md border border-sky-700/70 px-3 py-2 text-sm text-sky-300 hover:bg-sky-950"
        @click="dispatchWakeups"
      >
        Dispatch wakeups
      </button>
    </div>

    <section
      v-if="teamsData?.length"
      class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"
    >
      <h2 class="text-sm font-semibold text-zinc-200">
        Teams catalog
      </h2>
      <p class="mt-1 text-xs text-zinc-500">
        Install a team from Staff to seed specialists with department metadata.
      </p>
      <div class="mt-3 flex flex-wrap gap-2">
        <span
          v-for="team in teamsData"
          :key="team.slug"
          class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300"
        >
          {{ team.name }} ({{ team.roles.length }} roles)
        </span>
      </div>
    </section>

    <div
      v-if="pending"
      class="text-sm text-zinc-500"
    >
      Loading work issues...
    </div>

    <WorkIssueKanban
      v-else
      :issues="issues"
      @update-status="updateIssueStatus"
    />
  </div>
</template>
