<script setup lang="ts">
import type { MemoryRecord } from '~/types/api'

type ImportItem = {
  source?: string
  status?: string
  title?: string
  message?: string
  memory_id?: string
}

type ImportResult = {
  created: number
  skipped: number
  failed: number
  items: ImportItem[]
}

type LearnUrlResult = {
  url: string
  title: string
  chunks: number
  indexed: number
  type: 'youtube' | 'web'
}

type RecentResponse = {
  data?: MemoryRecord[]
}

const api = useApi()

// ── Learn from URL (single URL — YouTube transcript or web page) ──
const learnUrlInput = ref('')
const learnLoading = ref(false)
const learnError = ref('')
const learnProgress = ref('')
const learnResults = ref<LearnUrlResult[]>([])

async function learnFromUrl() {
  const url = learnUrlInput.value.trim()
  if (!url) return
  learnLoading.value = true
  learnError.value = ''
  learnProgress.value = (url.includes('youtube.com') || url.includes('youtu.be'))
    ? 'Fetching YouTube transcript…'
    : 'Fetching and reading page…'
  try {
    const result = await api.post('/knowledge/learn-url', { url }) as LearnUrlResult
    learnResults.value.unshift(result)
    learnUrlInput.value = ''
    await refresh()
  }
  catch (e) {
    learnError.value = e instanceof Error ? e.message : 'Failed to learn from URL.'
  }
  finally {
    learnLoading.value = false
    learnProgress.value = ''
  }
}

function onLearnKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') learnFromUrl()
}

function typeIcon(type: 'youtube' | 'web') {
  return type === 'youtube' ? '▶' : '🔗'
}

// ── Batch URL import ──
const urlInput = ref('')
const tagsInput = ref('')
const note = ref('')
const importing = ref<'urls' | 'codex' | 'claude' | null>(null)
const result = ref<ImportResult | null>(null)
const error = ref('')

const { data: recent, pending, refresh } = await useFetch<RecentResponse>(
  apiUrl('/knowledge/recent'),
  { server: false },
)

const parsedUrls = computed(() =>
  urlInput.value
    .split(/\s+/)
    .map(v => v.trim())
    .filter(Boolean),
)

const parsedTags = computed(() =>
  tagsInput.value
    .split(',')
    .map(v => v.trim())
    .filter(Boolean),
)

const resultTitle = computed(() => {
  if (!result.value) return ''
  if (importing.value === 'codex') return 'Imported Codex memory'
  if (importing.value === 'claude') return 'Imported Claude memory'
  return 'Imported URLs'
})

async function importUrls() {
  if (parsedUrls.value.length === 0) {
    error.value = 'Paste at least one URL first.'
    return
  }

  await runImport('urls', () => api.post('/knowledge/urls', {
    urls: parsedUrls.value,
    tags: parsedTags.value,
    note: note.value || null,
  }) as Promise<ImportResult>)
}

async function importMemory(source: 'codex' | 'claude') {
  await runImport(source, () => api.post('/knowledge/import-memory', { source }) as Promise<ImportResult>)
}

async function runImport(kind: 'urls' | 'codex' | 'claude', action: () => Promise<ImportResult>) {
  error.value = ''
  result.value = null
  importing.value = kind
  try {
    result.value = await action()
    await refresh()
  }
  catch (e) {
    error.value = e instanceof Error ? e.message : 'Import failed.'
  }
  finally {
    importing.value = null
  }
}

function statusClass(status?: string) {
  if (status === 'imported') return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
  if (status === 'partial') return 'border-amber-500/30 bg-amber-500/10 text-amber-200'
  if (status === 'skipped') return 'border-zinc-700 bg-zinc-900 text-zinc-300'
  return 'border-rose-500/30 bg-rose-500/10 text-rose-200'
}
</script>

<template>
  <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">

    <!-- ── Learn from URL (YouTube transcript / web article) ──────────── -->
    <section class="min-w-0 space-y-4 rounded-2xl border border-emerald-500/20 bg-zinc-950/80 p-4 sm:p-5 lg:col-span-2">
      <div>
        <h2 class="text-lg font-semibold text-zinc-100">Learn from URL</h2>
        <p class="text-sm text-zinc-500">
          Paste a YouTube video link or any article / documentation URL. BosskuAI will fetch the content,
          extract all readable text, chunk it, and store it in memory so every agent can retrieve and cite it.
        </p>
      </div>

      <div class="flex gap-3">
        <input
          v-model="learnUrlInput"
          type="url"
          class="min-w-0 flex-1 rounded-xl border border-zinc-800 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-100 outline-none placeholder:text-zinc-600 focus:border-emerald-400"
          placeholder="https://youtube.com/watch?v=… or https://docs.example.com/guide"
          :disabled="learnLoading"
          @keydown="onLearnKeydown"
        >
        <button
          type="button"
          class="shrink-0 rounded-xl bg-emerald-500 px-5 py-2 text-sm font-semibold text-zinc-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="learnLoading || !learnUrlInput.trim()"
          @click="learnFromUrl"
        >
          <span v-if="learnLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-zinc-900/30 border-t-zinc-900" />
          <span v-else>Learn</span>
        </button>
      </div>

      <p v-if="learnProgress" class="text-xs text-sky-300">{{ learnProgress }}</p>
      <p v-if="learnError" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">{{ learnError }}</p>

      <ul v-if="learnResults.length" class="space-y-2">
        <li
          v-for="r in learnResults"
          :key="r.url"
          class="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-3"
        >
          <div class="flex items-center gap-2">
            <span
              class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase"
              :class="r.type === 'youtube' ? 'bg-rose-500/15 text-rose-300' : 'bg-sky-500/15 text-sky-300'"
            >
              {{ typeIcon(r.type) }} {{ r.type }}
            </span>
            <span class="text-xs text-emerald-400">{{ r.chunks }} chunks indexed</span>
          </div>
          <p class="mt-1 text-sm font-medium text-zinc-100">{{ r.title }}</p>
          <a :href="r.url" target="_blank" rel="noopener" class="mt-0.5 block truncate text-xs text-zinc-600 hover:text-zinc-400">{{ r.url }}</a>
        </li>
      </ul>
    </section>

    <section class="min-w-0 space-y-4 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-4 sm:p-5">
      <div class="flex items-center justify-between gap-2">
        <div>
          <h2 class="text-lg font-semibold text-zinc-100">Dump URLs</h2>
          <p class="text-sm text-zinc-500">One URL per line works best. Batch imports keep going when one source fails.</p>
        </div>
        <button
          type="button"
          class="shrink-0 rounded-xl border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:border-emerald-400 hover:text-emerald-200"
          @click="refresh()"
        >
          Refresh
        </button>
      </div>

      <textarea
        v-model="urlInput"
        rows="8"
        class="w-full rounded-xl border border-zinc-800 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none ring-emerald-400/40 placeholder:text-zinc-600 focus:border-emerald-400 focus:ring-4"
        placeholder="Paste URLs from Google, YouTube, or any article..."
      />

      <div class="grid gap-3 md:grid-cols-2">
        <input
          v-model="tagsInput"
          class="rounded-xl border border-zinc-800 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-100 outline-none placeholder:text-zinc-600 focus:border-emerald-400"
          placeholder="Optional tags, comma separated"
        >
        <input
          v-model="note"
          class="rounded-xl border border-zinc-800 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-100 outline-none placeholder:text-zinc-600 focus:border-emerald-400"
          placeholder="Optional note"
        >
      </div>

      <div class="flex flex-wrap gap-3">
        <button
          type="button"
          class="rounded-xl bg-emerald-400 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="importing !== null"
          @click="importUrls"
        >
          {{ importing === 'urls' ? 'Importing...' : 'Import URLs' }}
        </button>
        <button
          type="button"
          class="rounded-xl border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:border-emerald-400 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="importing !== null"
          @click="importMemory('codex')"
        >
          {{ importing === 'codex' ? 'Importing...' : 'Import Codex Memory' }}
        </button>
        <button
          type="button"
          class="rounded-xl border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:border-emerald-400 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="importing !== null"
          @click="importMemory('claude')"
        >
          {{ importing === 'claude' ? 'Importing...' : 'Import Claude Memory' }}
        </button>
      </div>

      <p v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
        {{ error }}
      </p>

      <div v-if="result" class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
        <h3 class="text-sm font-semibold text-zinc-100">{{ resultTitle }}</h3>
        <div class="mt-3 flex flex-wrap gap-2 text-xs">
          <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-emerald-200">Created {{ result.created }}</span>
          <span class="rounded-full bg-zinc-800 px-3 py-1 text-zinc-300">Skipped {{ result.skipped }}</span>
          <span class="rounded-full bg-rose-500/15 px-3 py-1 text-rose-200">Failed {{ result.failed }}</span>
        </div>
        <ul class="mt-4 space-y-2">
          <li
            v-for="(item, i) in result.items"
            :key="`${item.source}-${i}`"
            class="rounded-xl border px-3 py-2 text-xs"
            :class="statusClass(item.status)"
          >
            <div class="font-medium">{{ item.title || item.source || 'Import item' }}</div>
            <div class="mt-1 opacity-80">{{ item.status }}<span v-if="item.message"> · {{ item.message }}</span></div>
          </li>
        </ul>
      </div>
    </section>

    <section class="min-w-0 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-4 sm:p-5">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-zinc-100">Recent knowledge</h2>
          <p class="text-sm text-zinc-500">Active rows stored in Bossku memory.</p>
        </div>
        <span class="rounded-full border border-zinc-800 px-3 py-1 text-xs text-zinc-500">
          {{ recent?.data?.length ?? 0 }} shown
        </span>
      </div>

      <UiSkeleton v-if="pending" class="mt-4 h-40 w-full" />
      <UiEmptyState
        v-else-if="!recent?.data?.length"
        class="mt-4"
        title="No imported knowledge yet."
        hint="Paste a URL or import local tool memory to start."
      />
      <ul v-else class="mt-4 space-y-3">
        <li
          v-for="item in recent.data"
          :key="item.id"
          class="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-semibold text-zinc-100">{{ item.human_summary || item.content?.slice(0, 100) || 'Knowledge item' }}</p>
            <span class="shrink-0 rounded-full bg-zinc-800 px-2 py-1 text-[11px] text-zinc-400">{{ item.source || 'knowledge' }}</span>
          </div>
          <p class="mt-2 break-words text-sm text-zinc-400">{{ item.content }}</p>
          <div class="mt-3 flex flex-wrap gap-1">
            <span
              v-for="tag in item.tags ?? []"
              :key="tag"
              class="rounded-full border border-zinc-700 px-2 py-0.5 text-[11px] text-zinc-500"
            >
              {{ tag }}
            </span>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
