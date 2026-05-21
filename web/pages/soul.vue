<script setup lang="ts">
import type { SoulVersion } from '~/types/api'

definePageMeta({ layout: 'default' })

const api = useApi()
const base = useApiBase()

interface SoulData {
  content: string
  version: string
  suggestions?: string[]
}

const { data: soulData, pending, refresh } = await useFetch<SoulData>(`${base}/api/soul`, { server: false })
const { data: versionsData, refresh: refreshVersions } = useFetch<{ data: SoulVersion[] }>(`${base}/api/soul/versions`, { server: false })

const suggestions = ref<string[]>([])
watch(soulData, (v) => {
  suggestions.value = v?.suggestions ?? []
}, { immediate: true })

const saving = ref(false)
const toast = useToast()

async function handleSave(content: string, changeSummary: string) {
  saving.value = true
  try {
    await api.put('/soul', { content, change_summary: changeSummary })
    await Promise.all([refresh(), refreshVersions()])
    toast.success('Soul updated successfully.')
  }
  catch {
    toast.error('Failed to save soul.')
  }
  finally {
    saving.value = false
  }
}

async function acceptSuggestion(suggestion: string) {
  const currentContent = soulData.value?.content ?? ''
  await handleSave(currentContent + '\n\n' + suggestion, 'AI suggestion applied')
  suggestions.value = suggestions.value.filter(s => s !== suggestion)
  toast.info('Suggestion applied to soul.')
}

function dismissSuggestion(suggestion: string) {
  suggestions.value = suggestions.value.filter(s => s !== suggestion)
  toast.info('Suggestion dismissed.')
}

const versions = computed(() => versionsData.value?.data ?? [])
</script>

<template>
  <div class="grid gap-8 lg:grid-cols-3">
    <!-- Main editor -->
    <div class="lg:col-span-2 space-y-6">
      <div>
        <h1 class="text-xl font-semibold">Soul</h1>
        <p class="text-sm text-zinc-400">BosskuAI's core values and operating principles.</p>
      </div>

      <!-- Suggestions -->
      <div v-if="suggestions.length" class="space-y-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">AI Suggestions</h2>
        <SoulSuggestionCard
          v-for="(s, i) in suggestions"
          :key="i"
          :suggestion="(s as string)"
          @accept="acceptSuggestion(s as string)"
          @dismiss="dismissSuggestion(s as string)"
        />
      </div>

      <UiSkeleton v-if="pending" class="h-64 w-full" />

      <template v-else>
        <div v-if="saveError" class="rounded-lg border border-red-800 bg-red-950/30 px-3 py-2 text-sm text-red-400">
          {{ saveError }}
        </div>

        <div v-if="saving" class="text-xs text-zinc-500">Saving…</div>

        <SoulEditor
          v-if="soulData"
          :content="soulData.content"
          :version="soulData.version"
          @save="handleSave"
        />
        <UiEmptyState v-else title="No soul content found." hint="Initialise soul content via the API." />
      </template>
    </div>

    <!-- Version timeline -->
    <aside class="space-y-4">
      <h2 class="text-sm font-semibold text-zinc-300">Version history</h2>
      <SoulVersionTimeline :versions="versions" />
    </aside>
  </div>
</template>
