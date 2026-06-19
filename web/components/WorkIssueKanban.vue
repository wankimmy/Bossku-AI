<script setup lang="ts">
import { computed } from 'vue'
import type { KanbanStatus, WorkIssue } from '~/types/api'

const props = defineProps<{
  issues: WorkIssue[]
  updatingId?: string | null
}>()

const emit = defineEmits<{
  (event: 'update-status', id: string, status: KanbanStatus): void
}>()

const statuses: { value: KanbanStatus; label: string }[] = [
  { value: 'backlog', label: 'Backlog' },
  { value: 'todo', label: 'Todo' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'in_review', label: 'In Review' },
  { value: 'blocked', label: 'Blocked' },
  { value: 'done', label: 'Done' },
  { value: 'cancelled', label: 'Cancelled' },
]

const displayStatuses = computed(() => {
  const known = new Set(statuses.map(status => status.value))
  const extra = [...new Set(props.issues.map(issue => String(issue.status)))]
    .filter(status => status && !known.has(status as KanbanStatus))
    .map(status => ({ value: status as KanbanStatus, label: status.replaceAll('_', ' ') }))

  return [...statuses, ...extra]
})

const grouped = computed(() => {
  const map = new Map<string, WorkIssue[]>()
  for (const status of displayStatuses.value) {
    map.set(status.value, [])
  }
  for (const issue of props.issues) {
    const bucket = map.get(String(issue.status)) ?? []
    bucket.push(issue)
    map.set(String(issue.status), bucket)
  }
  return map
})

function changeStatus(issue: WorkIssue, event: Event) {
  const target = event.target as HTMLSelectElement | null
  const next = target?.value as KanbanStatus | undefined
  if (!next) return
  emit('update-status', issue.id, next)
}

function isIssueUpdating(issue: WorkIssue): boolean {
  return props.updatingId === issue.id
}
</script>

<template>
  <div
    class="grid gap-4 xl:grid-cols-4 2xl:grid-cols-7"
    data-testid="work-issue-kanban"
  >
    <section
      v-for="status in displayStatuses"
      :key="status.value"
      class="min-h-48 rounded-lg border border-zinc-800 bg-zinc-900 p-3"
    >
      <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">
          {{ status.label }}
        </h3>
        <span class="rounded bg-zinc-950 px-2 py-0.5 text-[11px] text-zinc-500">
          {{ grouped.get(status.value)?.length ?? 0 }}
        </span>
      </div>

      <div class="space-y-2">
        <article
          v-for="issue in grouped.get(status.value)"
          :key="issue.id"
          class="rounded-md border border-zinc-800 bg-zinc-950 p-3"
        >
          <div class="flex items-start justify-between gap-2">
            <h4 class="min-w-0 text-sm font-medium leading-snug text-zinc-100">
              {{ issue.title }}
            </h4>
            <span class="shrink-0 rounded border border-zinc-700 px-1.5 py-0.5 text-[11px] text-zinc-400">
              {{ issue.priority }}
            </span>
          </div>

          <p
            v-if="issue.description"
            class="mt-2 line-clamp-3 text-xs leading-relaxed text-zinc-400"
          >
            {{ issue.description }}
          </p>

          <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <span class="min-w-0 truncate text-xs text-zinc-500">
              {{ issue.assignee_agent?.display_name ?? issue.assignee_role_slug ?? 'unassigned' }}
            </span>
            <span
              v-if="issue.parent_issue?.title"
              class="truncate text-[11px] text-zinc-600"
              :title="issue.parent_issue.title"
            >
              ↳ {{ issue.parent_issue.title }}
            </span>
            <select
              class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs text-zinc-200"
              :data-testid="`issue-${issue.id}-status`"
              :value="issue.status"
              :disabled="isIssueUpdating(issue)"
              @change="event => changeStatus(issue, event)"
            >
              <option
                v-for="option in statuses"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>
