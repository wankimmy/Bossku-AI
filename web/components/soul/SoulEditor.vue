<script setup lang="ts">
const props = defineProps<{
  content: string
  version: string
}>()

const emit = defineEmits<{
  save: [content: string, changeSummary: string]
}>()

const editedContent = ref(props.content)
const changeSummary = ref('')
const showDiff = ref(false)

watch(() => props.content, (v) => {
  editedContent.value = v
})

function save() {
  emit('save', editedContent.value, changeSummary.value)
}

const beforeLines = computed(() => props.content.split('\n'))
const afterLines = computed(() => editedContent.value.split('\n'))
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <span class="text-xs text-zinc-500">Current version: <span class="font-mono text-zinc-300">{{ version }}</span></span>
      <button
        type="button"
        class="text-xs text-zinc-400 underline hover:text-zinc-200"
        @click="showDiff = !showDiff"
      >
        {{ showDiff ? 'Hide diff' : 'Diff preview' }}
      </button>
    </div>

    <textarea
      v-model="editedContent"
      rows="16"
      class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-sm text-zinc-200 placeholder-zinc-600 focus:border-zinc-500 focus:outline-none resize-y"
      placeholder="Soul content…"
    />

    <!-- Diff preview -->
    <div v-if="showDiff" class="grid grid-cols-2 gap-2 rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-xs font-mono overflow-x-auto">
      <div>
        <p class="mb-1 font-sans text-zinc-500">Before</p>
        <pre class="whitespace-pre-wrap text-red-400">{{ beforeLines.join('\n') }}</pre>
      </div>
      <div>
        <p class="mb-1 font-sans text-zinc-500">After</p>
        <pre class="whitespace-pre-wrap text-emerald-400">{{ afterLines.join('\n') }}</pre>
      </div>
    </div>

    <div class="space-y-2">
      <label class="block text-xs text-zinc-400">Change summary</label>
      <input
        v-model="changeSummary"
        type="text"
        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200 placeholder-zinc-600 focus:border-zinc-500 focus:outline-none"
        placeholder="Briefly describe what changed…"
      >
    </div>

    <button
      type="button"
      :disabled="!changeSummary.trim()"
      class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600 disabled:opacity-40 transition"
      @click="save"
    >
      Save version
    </button>
  </div>
</template>
