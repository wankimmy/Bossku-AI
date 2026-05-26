<script setup lang="ts">
import type { Approval } from '~/types/api'
import { isIdempotentApprovalOutcome } from '~/utils/approvalDecision'
import { assessFileChange } from '~/utils/approvalReview'

const props = defineProps<{ approval: Approval }>()
const emit = defineEmits<{ approve: []; reject: [] }>()

const api = useApi()
const loading = ref(false)
const note = ref('')

const requestChangesDisabled = computed(() => loading.value || !note.value.trim())

const fileReview = computed(() => {
  if (props.approval.operation_type === 'terminal_command') {
    return { blocked: false, reason: null as string | null }
  }
  const ev = props.approval.evidence ?? {}

  return assessFileChange(
    {
      path: String(ev.path ?? ''),
      change_type: String(ev.change_type ?? 'modified'),
      before: String(ev.before ?? ''),
      after: String(ev.after ?? ''),
    },
    props.approval.review_blocked,
    props.approval.review_block_reason,
  )
})

async function doApprove() {
  if (loading.value || fileReview.value.blocked) return
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/approve`, { note: note.value || undefined })
    emit('approve')
  }
  catch (err) {
    if (isIdempotentApprovalOutcome(err, 'approve')) {
      emit('approve')
      return
    }
    throw err
  }
  finally { loading.value = false }
}

async function doRequestChanges() {
  if (loading.value || !note.value.trim()) return
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/reject`, { note: note.value.trim() })
    emit('reject')
  }
  catch (err) {
    if (isIdempotentApprovalOutcome(err, 'reject')) {
      emit('reject')
      return
    }
    throw err
  }
  finally { loading.value = false }
}
</script>

<template>
  <div class="rounded-lg border border-yellow-700 bg-yellow-950/30 p-4">
    <div class="flex items-start gap-3">
      <span class="text-yellow-400 text-lg shrink-0">⚠</span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-yellow-300">
          This step requires approval —
          <span class="font-mono">{{ approval.operation_type }}</span>
        </p>
        <p v-if="approval.description" class="text-sm text-yellow-200/70 mt-1">{{ approval.description }}</p>
        <p v-if="fileReview.blocked" class="mt-2 text-xs text-amber-300">
          {{ fileReview.reason }}
        </p>
        <div class="mt-3">
          <textarea
            v-model="note"
            rows="2"
            data-testid="code-review-instructions"
            class="mb-2 w-full bg-zinc-900/80 border border-zinc-700 rounded px-2 py-1.5 text-xs text-zinc-100 placeholder-zinc-600"
            placeholder="Code review instructions (required to request changes)"
          />
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-xs rounded bg-emerald-900/70 text-emerald-300 border border-emerald-700 hover:bg-emerald-800/70 disabled:opacity-50"
              :disabled="loading || fileReview.blocked"
              :title="fileReview.blocked ? (fileReview.reason ?? 'Change blocked') : undefined"
              @click="doApprove"
            >
              {{ fileReview.blocked ? 'Approve blocked' : 'Approve' }}
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-xs rounded bg-red-900/70 text-red-300 border border-red-700 hover:bg-red-800/70 disabled:opacity-50"
              :disabled="requestChangesDisabled"
              data-testid="request-changes-btn"
              @click="doRequestChanges"
            >
              Request changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
