<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import type { Approval } from '~/types/api'
import { isAlreadyDecidedResponse, isIdempotentApprovalOutcome } from '~/utils/approvalDecision'
import { assessFileChange } from '~/utils/approvalReview'
import SideBySideDiffViewer from './SideBySideDiffViewer.vue'

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

const api = useApi()
const note = ref('')
const loading = ref(false)

watch(() => props.approval?.id, () => {
  note.value = ''
})

const evidence = computed(() => props.approval?.evidence ?? {})

const isTerminalCommand = computed(() => props.approval?.operation_type === 'terminal_command')

const isDelete = computed(() => {
  const ev = evidence.value
  if (isTerminalCommand.value) {
    const cmd = String(ev.command ?? '').toLowerCase()
    return cmd.includes('restore') || cmd.includes('checkout') || cmd.includes('delete')
  }
  return ev.change_type === 'deleted'
})

const diffText = computed(() => {
  const ev = evidence.value
  if (typeof ev.diff === 'string' && ev.diff.trim()) return ev.diff
  return ''
})

const commandText = computed(() => {
  if (!isTerminalCommand.value) return ''
  return String(evidence.value.command ?? '')
})

const showDiffViewer = computed(() =>
  !isTerminalCommand.value && (
    diffText.value !== ''
    || String(evidence.value.before ?? '') !== ''
    || String(evidence.value.after ?? '') !== ''
  ),
)

const fileReview = computed(() => {
  if (!props.approval || isTerminalCommand.value) {
    return { blocked: false, reason: null as string | null, stats: { added: 0, removed: 0, unchanged: 0 } }
  }

  return assessFileChange(
    {
      path: String(evidence.value.path ?? ''),
      change_type: String(evidence.value.change_type ?? 'modified'),
      before: String(evidence.value.before ?? ''),
      after: String(evidence.value.after ?? ''),
      diff: diffText.value,
    },
    props.approval.review_blocked,
    props.approval.review_block_reason,
  )
})

const approveBlocked = computed(() => fileReview.value.blocked)

async function doApprove() {
  if (!props.approval || loading.value || approveBlocked.value) return
  loading.value = true
  try {
    const res = await api.post(`/approvals/${props.approval.id}/approve`, { note: note.value || undefined })
    if (isAlreadyDecidedResponse(res)) {
      // already granted — advance queue
    }
    emit('approve', note.value)
    note.value = ''
  }
  catch (err) {
    if (isIdempotentApprovalOutcome(err, 'approve')) {
      emit('approve', note.value)
      note.value = ''
      return
    }
    throw err
  }
  finally {
    loading.value = false
  }
}

async function doReject() {
  if (!props.approval || loading.value) return
  loading.value = true
  try {
    const res = await api.post(`/approvals/${props.approval.id}/reject`, { note: note.value || undefined })
    if (isAlreadyDecidedResponse(res)) {
      // already rejected — advance queue
    }
    emit('reject', note.value)
    note.value = ''
  }
  catch (err) {
    if (isIdempotentApprovalOutcome(err, 'reject')) {
      emit('reject', note.value)
      note.value = ''
      return
    }
    throw err
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
      class="fixed inset-0 z-[70] flex items-center justify-center bg-black/75 p-3 sm:p-4"
      role="dialog"
      aria-modal="true"
      data-testid="change-approval-modal"
    >
      <div
        class="flex h-[min(92vh,900px)] w-full max-w-[min(96vw,1400px)] flex-col overflow-hidden rounded-xl border shadow-2xl"
        :class="isDelete ? 'border-red-500/60 bg-zinc-900' : 'border-amber-500/50 bg-zinc-900'"
        data-testid="change-approval-dialog"
        @click.stop
      >
        <div class="shrink-0 border-b border-zinc-700 px-5 py-3">
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
              <p v-if="approval.description" class="mt-1.5 text-sm text-zinc-300 line-clamp-2">
                {{ approval.description }}
              </p>
              <p v-if="approval.risk_level" class="mt-1 text-xs">
                Risk:
                <span class="font-semibold" :class="riskCls(approval.risk_level)">{{ approval.risk_level }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-3 px-5 py-3">
          <div
            v-if="evidence.why || evidence.summary"
            class="shrink-0 space-y-2 text-sm text-zinc-400"
          >
            <div v-if="evidence.why">
              <span class="text-xs uppercase text-zinc-500">Why</span>
              <p class="mt-0.5">{{ evidence.why }}</p>
            </div>
            <div v-if="evidence.summary">
              <span class="text-xs uppercase text-zinc-500">Summary</span>
              <p class="mt-0.5">{{ evidence.summary }}</p>
            </div>
          </div>

          <SideBySideDiffViewer
            v-if="showDiffViewer || isTerminalCommand"
            class="min-h-0 flex-1"
            :path="String(evidence.path ?? '')"
            :change-type="String(evidence.change_type ?? 'modified')"
            :diff="diffText"
            :before="String(evidence.before ?? '')"
            :after="String(evidence.after ?? '')"
            :command-text="commandText"
            :review-blocked="fileReview.blocked"
            :review-block-reason="fileReview.reason"
          />

          <div class="shrink-0">
            <label class="text-xs text-zinc-500 uppercase tracking-wide block mb-1">
              Comment for executor (optional)
            </label>
            <textarea
              v-model="note"
              rows="2"
              class="w-full bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-zinc-600 outline-none focus:border-zinc-500"
              placeholder="e.g. Use routes/api.php instead, or skip this delete…"
            />
          </div>
        </div>

        <div class="shrink-0 flex gap-3 px-5 py-3 border-t border-zinc-700">
          <button
            type="button"
            class="flex-1 py-2.5 text-sm rounded-lg bg-emerald-900/70 text-emerald-300 border border-emerald-700 hover:bg-emerald-800/70 disabled:opacity-50"
            :disabled="loading || submitting || approveBlocked"
            :title="approveBlocked ? (fileReview.reason ?? 'Change blocked') : undefined"
            @click="doApprove"
          >
            {{ approveBlocked ? 'Approve blocked' : 'Approve & apply' }}
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
