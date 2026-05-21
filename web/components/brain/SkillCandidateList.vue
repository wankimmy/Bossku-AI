<script setup lang="ts">
import type { SkillCandidate } from '~/types/api'

const props = defineProps<{ candidates: SkillCandidate[] }>()
const emit = defineEmits<{ approve: [id: string]; reject: [id: string] }>()

const api = useApi()

async function approve(id: string) {
  await api.post(`/skill-candidates/${id}/approve`)
  emit('approve', id)
}

async function reject(id: string) {
  await api.post(`/skill-candidates/${id}/reject`)
  emit('reject', id)
}

const statusCls = (s: string) => {
  switch (s) {
    case 'approved': return 'bg-emerald-900/50 text-emerald-300 border-emerald-800'
    case 'rejected': return 'bg-red-900/50 text-red-300 border-red-800'
    default: return 'bg-yellow-900/50 text-yellow-300 border-yellow-800'
  }
}
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div v-if="candidates.length === 0" class="col-span-full text-sm text-zinc-500 py-4 text-center">
      No skill candidates.
    </div>
    <div
      v-for="c in candidates"
      :key="c.id"
      class="rounded-lg bg-zinc-900 border border-zinc-800 p-4 flex flex-col gap-3"
    >
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-sm font-semibold text-zinc-100">{{ c.name || c.id }}</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs border" :class="statusCls(c.approval_status)">
            {{ c.approval_status }}
          </span>
        </div>
        <p v-if="c.description" class="text-xs text-zinc-400">{{ c.description }}</p>
        <p v-if="c.category" class="text-xs text-zinc-500 mt-1">Category: {{ c.category }}</p>
        <p v-if="c.confidence != null" class="text-xs text-zinc-500">
          Confidence: {{ (c.confidence * 100).toFixed(0) }}%
        </p>
      </div>
      <div v-if="c.approval_status === 'pending'" class="flex gap-2 mt-auto">
        <button
          type="button"
          class="flex-1 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300 border border-emerald-800 hover:bg-emerald-800/50"
          @click="approve(c.id)"
        >
          Approve
        </button>
        <button
          type="button"
          class="flex-1 py-1 text-xs rounded bg-red-900/50 text-red-300 border border-red-800 hover:bg-red-800/50"
          @click="reject(c.id)"
        >
          Reject
        </button>
      </div>
    </div>
  </div>
</template>
