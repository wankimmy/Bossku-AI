<script setup lang="ts">
import type { Approval } from '~/types/api'

const props = defineProps<{ approval: Approval }>()
const emit = defineEmits<{ approve: []; reject: [] }>()

const api = useApi()
const loading = ref(false)
const note = ref('')

async function doApprove() {
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/approve`, { note: note.value || undefined })
    emit('approve')
  }
  finally { loading.value = false }
}

async function doReject() {
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/reject`, { note: note.value || undefined })
    emit('reject')
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
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-xs rounded bg-emerald-900/70 text-emerald-300 border border-emerald-700 hover:bg-emerald-800/70 disabled:opacity-50"
            :disabled="loading"
            @click="doApprove"
          >
            Approve
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-xs rounded bg-red-900/70 text-red-300 border border-red-700 hover:bg-red-800/70 disabled:opacity-50"
            :disabled="loading"
            @click="doReject"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
