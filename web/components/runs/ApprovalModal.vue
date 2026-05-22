<script setup lang="ts">
import { ref } from 'vue'
import type { Approval } from '~/types/api'
import SideBySideDiffViewer from '../SideBySideDiffViewer.vue'

const props = defineProps<{ approval: Approval }>()
const emit = defineEmits<{ approve: []; reject: []; close: [] }>()

const api = useApi()
const loading = ref(false)
const note = ref('')

async function doApprove() {
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/approve`, { note: note.value || undefined })
    emit('approve')
    emit('close')
  }
  finally { loading.value = false }
}

async function doReject() {
  loading.value = true
  try {
    await api.post(`/approvals/${props.approval.id}/reject`, { note: note.value || undefined })
    emit('reject')
    emit('close')
  }
  finally { loading.value = false }
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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="emit('close')">
      <div class="w-full max-w-lg bg-zinc-900 border border-zinc-700 rounded-xl shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-700">
          <div class="flex items-center gap-2">
            <span class="text-yellow-400">⚠</span>
            <h2 class="text-sm font-semibold text-zinc-100">Approval Required</h2>
          </div>
          <button type="button" class="text-zinc-500 hover:text-zinc-100 text-xs" @click="emit('close')">✕</button>
        </div>

        <!-- Body -->
        <div class="px-5 py-4 space-y-4">
          <div>
            <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Operation</div>
            <div class="font-mono text-sm text-zinc-100">{{ approval.operation_type }}</div>
          </div>

          <div v-if="approval.description">
            <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Description</div>
            <p class="text-sm text-zinc-300">{{ approval.description }}</p>
          </div>

          <div v-if="approval.risk_level">
            <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Risk Level</div>
            <span class="text-sm font-semibold" :class="riskCls(approval.risk_level)">
              {{ approval.risk_level }}
            </span>
          </div>

          <SideBySideDiffViewer
            v-if="approval.operation_type === 'file_write' || approval.operation_type === 'terminal_command'"
            class="max-h-64"
            :path="String(approval.evidence?.path ?? '')"
            :change-type="String(approval.evidence?.change_type ?? '')"
            :diff="typeof approval.evidence?.diff === 'string' ? approval.evidence.diff : ''"
            :before="String(approval.evidence?.before ?? '')"
            :after="String(approval.evidence?.after ?? '')"
            :command-text="approval.operation_type === 'terminal_command' ? String(approval.evidence?.command ?? '') : ''"
          />
          <div v-else-if="approval.evidence">
            <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Evidence</div>
            <pre class="text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 rounded p-3 overflow-x-auto max-h-32">{{ JSON.stringify(approval.evidence, null, 2) }}</pre>
          </div>

          <div>
            <label class="text-xs text-zinc-500 uppercase tracking-wide block mb-1">Decision Note (optional)</label>
            <textarea
              v-model="note"
              rows="2"
              class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-2 text-sm text-zinc-100 placeholder-zinc-600 outline-none focus:border-zinc-500"
              placeholder="Add a note..."
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="flex gap-3 px-5 py-4 border-t border-zinc-700">
          <button
            type="button"
            class="flex-1 py-2 text-sm rounded bg-emerald-900/70 text-emerald-300 border border-emerald-700 hover:bg-emerald-800/70 disabled:opacity-50"
            :disabled="loading"
            @click="doApprove"
          >
            Approve
          </button>
          <button
            type="button"
            class="flex-1 py-2 text-sm rounded bg-red-900/70 text-red-300 border border-red-700 hover:bg-red-800/70 disabled:opacity-50"
            :disabled="loading"
            @click="doReject"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
