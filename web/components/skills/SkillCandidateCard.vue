<script setup lang="ts">
import type { SkillCandidate } from '~/types/api'

const props = defineProps<{
  candidate: SkillCandidate
}>()

const emit = defineEmits<{
  approved: [id: string]
  rejected: [id: string]
}>()

const api = useApi()
const loading = ref<'approve' | 'reject' | null>(null)
const localStatus = ref(props.candidate.approval_status)
const confirmPending = ref<'approve' | 'reject' | null>(null)

const RISKY_CATEGORIES = ['payment-gateway', 'security', 'deployment', 'auth']
const isRisky = computed(() => RISKY_CATEGORIES.includes(props.candidate.category ?? ''))

const statusConfig: Record<string, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-zinc-700 text-zinc-300' },
  pending_review: { label: 'Pending Review', class: 'bg-yellow-900/60 text-yellow-300' },
  approved: { label: 'Approved', class: 'bg-emerald-900/60 text-emerald-300' },
  rejected: { label: 'Rejected', class: 'bg-red-900/60 text-red-300' },
}

const currentStatus = computed(() => statusConfig[localStatus.value] ?? { label: localStatus.value, class: 'bg-zinc-700 text-zinc-300' })

const qualityScore = computed(() => {
  const c = props.candidate.confidence ?? 0
  return Math.round(c * 100)
})

function requestAction(action: 'approve' | 'reject') {
  if (isRisky.value) {
    confirmPending.value = action
  }
  else {
    performAction(action)
  }
}

async function performAction(action: 'approve' | 'reject') {
  confirmPending.value = null
  loading.value = action
  try {
    await api.post(`/skill-candidates/${props.candidate.id}/${action}`)
    localStatus.value = action === 'approve' ? 'approved' : 'rejected'
    emit(action === 'approve' ? 'approved' : 'rejected', props.candidate.id)
  }
  finally {
    loading.value = null
  }
}
</script>

<template>
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 space-y-3">
    <!-- Confirm dialog overlay -->
    <div v-if="confirmPending" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="w-full max-w-sm rounded-xl border border-zinc-700 bg-zinc-900 p-6 space-y-4">
        <p class="text-sm font-medium text-white">
          This is a <span class="text-red-400">{{ candidate.category }}</span> skill. Are you sure you want to
          <span :class="confirmPending === 'approve' ? 'text-emerald-400' : 'text-red-400'">{{ confirmPending }}</span> it?
        </p>
        <div class="flex gap-2 justify-end">
          <button type="button" class="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm text-zinc-300 hover:bg-zinc-800" @click="confirmPending = null">Cancel</button>
          <button
            type="button"
            :class="confirmPending === 'approve' ? 'bg-emerald-700 hover:bg-emerald-600' : 'bg-red-700 hover:bg-red-600'"
            class="rounded-lg px-3 py-1.5 text-sm text-white"
            @click="performAction(confirmPending!)"
          >
            Confirm {{ confirmPending }}
          </button>
        </div>
      </div>
    </div>

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <p class="font-semibold text-white">{{ candidate.name ?? 'Unnamed candidate' }}</p>
        <span v-if="candidate.category" class="mt-0.5 inline-block rounded border border-zinc-700 px-2 py-0.5 text-xs text-zinc-400">
          {{ candidate.category }}
        </span>
      </div>
      <span :class="currentStatus.class" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
        {{ currentStatus.label }}
      </span>
    </div>

    <!-- Risky warning -->
    <div v-if="isRisky" class="flex items-center gap-2 rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-xs text-red-400">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      Manual review required
    </div>

    <!-- Description -->
    <p v-if="candidate.description" class="text-sm text-zinc-400">{{ candidate.description }}</p>

    <!-- Quality score bar -->
    <div class="space-y-1">
      <div class="flex items-center justify-between text-xs text-zinc-400">
        <span>Quality score</span>
        <span>{{ qualityScore }}%</span>
      </div>
      <div class="h-1.5 w-full rounded-full bg-zinc-800">
        <div
          class="h-1.5 rounded-full transition-all"
          :class="qualityScore >= 70 ? 'bg-emerald-500' : qualityScore >= 40 ? 'bg-yellow-500' : 'bg-red-500'"
          :style="{ width: `${qualityScore}%` }"
        />
      </div>
    </div>

    <!-- Source runs -->
    <p v-if="candidate.source_run_id" class="text-xs text-zinc-500">
      Source run: <span class="font-mono text-zinc-400">{{ candidate.source_run_id }}</span>
    </p>

    <!-- Actions -->
    <div v-if="localStatus === 'pending_review' || localStatus === 'pending'" class="flex gap-2 pt-1">
      <button
        type="button"
        :disabled="loading !== null"
        class="flex-1 rounded-lg bg-emerald-800 px-3 py-1.5 text-sm font-medium text-emerald-100 hover:bg-emerald-700 disabled:opacity-50 transition"
        @click="requestAction('approve')"
      >
        {{ loading === 'approve' ? 'Approving…' : 'Approve' }}
      </button>
      <button
        type="button"
        :disabled="loading !== null"
        class="flex-1 rounded-lg bg-red-900/60 px-3 py-1.5 text-sm font-medium text-red-200 hover:bg-red-800 disabled:opacity-50 transition"
        @click="requestAction('reject')"
      >
        {{ loading === 'reject' ? 'Rejecting…' : 'Reject' }}
      </button>
    </div>
  </div>
</template>
