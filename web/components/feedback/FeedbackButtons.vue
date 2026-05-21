<script setup lang="ts">
const props = defineProps<{
  targetType: string
  targetId: string
}>()

const api = useApi()
const submitted = ref<'positive' | 'negative' | 'flag' | null>(null)
const loading = ref(false)

async function send(signal: 'positive' | 'negative' | 'flag') {
  if (loading.value || submitted.value) return
  loading.value = true
  try {
    await api.post('/feedback', {
      target_type: props.targetType,
      target_id: props.targetId,
      signal,
    })
    submitted.value = signal
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="inline-flex items-center gap-1">
    <template v-if="submitted">
      <span class="rounded-full bg-emerald-900/40 px-2 py-0.5 text-xs text-emerald-400">Thanks!</span>
    </template>
    <template v-else>
      <button
        type="button"
        title="Helpful"
        :disabled="loading"
        class="rounded p-1 text-zinc-400 transition hover:bg-zinc-800 hover:text-emerald-400 disabled:opacity-40"
        @click="send('positive')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
        </svg>
      </button>
      <button
        type="button"
        title="Not helpful"
        :disabled="loading"
        class="rounded p-1 text-zinc-400 transition hover:bg-zinc-800 hover:text-red-400 disabled:opacity-40"
        @click="send('negative')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v2a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5" />
        </svg>
      </button>
      <button
        type="button"
        title="Flag"
        :disabled="loading"
        class="rounded p-1 text-zinc-400 transition hover:bg-zinc-800 hover:text-yellow-400 disabled:opacity-40"
        @click="send('flag')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
        </svg>
      </button>
    </template>
  </div>
</template>
