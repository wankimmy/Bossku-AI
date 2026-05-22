<script setup lang="ts">
import type { Approval } from '~/types/api'

const props = defineProps<{
  open: boolean
  approval: Approval | null
  pendingCount: number
  submitting?: boolean
}>()

const emit = defineEmits<{
  approve: [note: string]
  reject: [note: string]
}>()

const note = ref('')
const api = useApi()
const loading = ref(false)

watch(() => props.approval?.id, () => {
  note.value = ''
})

const isDelete = computed(() => {
  const ev = props.approval?.evidence ?? {}
  if (props.approval?.operation_type === 'terminal_command') {
    const cmd = String(ev.command ?? '').toLowerCase()
    return cmd.includes('restore') || cmd.includes('checkout') || cmd.includes('delete')
  }
  return ev.change_type === 'deleted'
})

const diffText = computed(() => {
  const ev = props.approval?.evidence ?? {}
  if (typeof ev.diff === 'string' && ev.diff.trim()) return ev.diff
  if (props.approval?.operation_type === 'terminal_command') {
    return String(ev.command ?? '')
  }
  return ''
})

async function doApprove() {
  if (!props.approval || loading.value) return
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/approve`, { note: note.value || undefined })
    emit('approve', note.value)
    note.value = ''
  }
  finally {
    loading.value = false
  }
}

async function doReject() {
  if (!props.approval || loading.value) return
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/reject`, { note: note.value || undefined })
    emit('reject', note.value)
    note.value = ''
  }
  finally {
    loading.value = false
  }
}

const riskCls = (r?: string) => {
  switch (r) {
    case 'low': return 'text-emerald-300'
    case 'medium': return 'text-yellow-300'
    case 'high': return 'text-orange-300'
    case 'critical': return 'text-red-300'
    default: return 'text-zinc-400'
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && approval"
      class="fixed inset-0 z-[70] flex items-center justify-center bg-black/75 p-4"
      role="dialog"
      aria-modal="true"
      data-testid="change-approval-modal"
    >
      <div
        class="flex w-full max-w-2xl max-h-[90vh] flex-col overflow-hidden rounded-xl border shadow-2xl"
        :class="isDelete ? 'border-red-500/60 bg-zinc-900' : 'border-amber-500/50 bg-zinc-900'"
        @click.stop
      >
        <div class="shrink-0 border-b border-zinc-700 px-5 py-4">
          <div class="flex items-start gap-2">
            <span class="text-lg" :class="isDelete ? 'text-red-400' : 'text-amber-400'" aria-hidden="true">
              {{ isDelete ? '⚠' : '✎' }}
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="text-sm font-semibold text-zinc-100">
                Review before continuing
              </h2>
              <p class="mt-0.5 text-xs text-zinc-500">
                {{ pendingCount }} item(s) waiting · executor is paused until you decide
              </p>
              <p v-if="approval.description" class="mt-2 text-sm text-zinc-300">
                {{ approval.description }}
              </p>
              <p v-if="approval.risk_level" class="mt-1 text-xs">
                Risk:
                <span class="font-semibold" :class="riskCls(approval.risk_level)">{{ approval.risk_level }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-3">
          <div v-if="approval.evidence?.why" class="text-sm text-zinc-400">
            <span class="text-xs uppercase text-zinc-500">Why</span>
            <p class="mt-1">{{ approval.evidence.why }}</p>
          </div>
          <div v-if="approval.evidence?.summary" class="text-sm text-zinc-400">
            <span class="text-xs uppercase text-zinc-500">Summary</span>
            <p class="mt-1">{{ approval.evidence.summary }}</p>
          </div>
          <div v-if="diffText">
            <div class="text-xs uppercase text-zinc-500 mb-1">
              {{ approval.operation_type === 'terminal_command' ? 'Command' : 'Diff' }}
            </div>
            <pre class="text-xs text-zinc-300 bg-zinc-950 border border-zinc-800 rounded-lg p-3 overflow-x-auto max-h-48 whitespace-pre-wrap">{{ diffText }}</pre>
          </div>
          <div>
            <label class="text-xs text-zinc-500 uppercase tracking-wide block mb-1">
              Comment for executor (optional)
            </label>
            <textarea
              v-model="note"
              rows="3"
              class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-zinc-600 outline-none focus:border-zinc-500"
              placeholder="e.g. Use routes/api.php instead, or skip this delete…"
            />
          </div>
        </div>

        <div class="shrink-0 flex gap-3 px-5 py-4 border-t border-zinc-700">
          <button
            type="button"
            class="flex-1 py-2.5 text-sm rounded-lg bg-emerald-900/70 text-emerald-300 border border-emerald-700 hover:bg-emerald-800/70 disabled:opacity-50"
            :disabled="loading || submitting"
            @click="doApprove"
          >
            Approve & apply
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 text-sm rounded-lg bg-red-900/70 text-red-300 border border-red-700 hover:bg-red-800/70 disabled:opacity-50"
            :disabled="loading || submitting"
            @click="doReject"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
