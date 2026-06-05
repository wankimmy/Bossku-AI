<script setup lang="ts">
const props = defineProps<{
  run: Record<string, unknown>
}>()

const api = useApi()
const route = useRoute()

const childRuns = computed(() => {
  const children = props.run?.child_runs ?? props.run?.childRuns
  return Array.isArray(children) ? children as Record<string, unknown>[] : []
})

const supervisorStatus = ref<Record<string, unknown> | null>(null)
const polling = ref(false)

async function refreshSupervisor() {
  if (props.run.run_kind !== 'supervisor') return
  const id = String(props.run.id ?? route.params.id)
  try {
    supervisorStatus.value = await api.get(`/runs/${id}/supervisor`) as Record<string, unknown>
  }
  catch {
    supervisorStatus.value = null
  }
}

let timer: ReturnType<typeof setInterval> | null = null

onMounted(async () => {
  await refreshSupervisor()
  const active = childRuns.value.some(c => ['queued', 'running'].includes(String(c.status)))
  if (active || props.run.run_kind === 'supervisor') {
    polling.value = true
    timer = setInterval(refreshSupervisor, 4000)
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const fleet = computed(() => {
  const children = supervisorStatus.value?.children
  if (Array.isArray(children) && children.length) return children as Record<string, unknown>[]
  return childRuns.value
})
</script>

<template>
  <section v-if="run.run_kind === 'supervisor' || childRuns.length" class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 space-y-3">
    <div class="flex items-center justify-between gap-2">
      <h3 class="text-sm font-semibold text-zinc-200">
        Fleet supervisor
      </h3>
      <span v-if="supervisorStatus" class="text-xs text-zinc-500">
        {{ supervisorStatus.children_completed }}/{{ supervisorStatus.children_total }} done
        <template v-if="supervisorStatus.ready_to_synthesize"> · ready to merge</template>
      </span>
    </div>

    <p v-if="run.run_kind === 'supervisor'" class="text-xs text-zinc-500">
      Parent run coordinating isolated child worktrees.
    </p>

    <ul v-if="fleet.length" class="space-y-2">
      <li
        v-for="child in fleet"
        :key="String(child.id)"
        class="rounded border border-zinc-800 px-3 py-2 text-xs space-y-1"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="text-zinc-300 font-mono">#{{ child.supervisor_slot }} — {{ String(child.id).slice(0, 8) }}</span>
          <span class="uppercase" :class="child.status === 'completed' ? 'text-emerald-400' : child.status === 'failed' ? 'text-red-400' : 'text-amber-400'">
            {{ child.status }}
          </span>
        </div>
        <p v-if="child.prompt" class="text-zinc-500 truncate">{{ child.prompt }}</p>
        <p v-if="child.workspace && typeof child.workspace === 'object'" class="text-zinc-600 font-mono truncate">
          {{ (child.workspace as Record<string, unknown>).branch_name }}
        </p>
        <NuxtLink :to="`/runs/${child.id}`" class="inline-block text-sky-400 hover:underline">
          Open child run
        </NuxtLink>
      </li>
    </ul>
  </section>
</template>
