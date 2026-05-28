<script setup lang="ts">
interface MemoryItem {
  id: string
  type?: string
  content?: string
  human_summary?: string | null
  confidence?: number
  source?: string | null
  tags?: string[] | null
  is_active?: boolean
  usage_count?: number
  updated_at?: string
}

interface PaginatedResponse {
  data: MemoryItem[]
  total: number
  current_page: number
  last_page: number
  next_page_url: string | null
}

// ── state ────────────────────────────────────────────────────────────────────
const q         = ref('')
const typeFilter = ref('')
const activeFilter = ref<'all' | 'active' | 'inactive'>('all')
const sortBy    = ref<'newest' | 'oldest' | 'confidence' | 'usage'>('newest')

const page      = ref(1)
const items     = ref<MemoryItem[]>([])
const total     = ref(0)
const lastPage  = ref(1)
const loading   = ref(false)
const appending = ref(false)

const semanticQ       = ref('')
const semanticResults = ref<MemoryItem[]>([])
const semanticMode    = ref(false)
const semanticLoading = ref(false)

const expandedId = ref<string | null>(null)
const deletingId = ref<string | null>(null)
const togglingId = ref<string | null>(null)

const MEMORY_TYPES = [
  'pattern', 'preference', 'fact', 'procedure',
  'context', 'domain', 'rule', 'insight',
]

const TYPE_COLORS: Record<string, string> = {
  pattern:    'bg-violet-900/50 text-violet-300 border-violet-700/40',
  preference: 'bg-blue-900/50 text-blue-300 border-blue-700/40',
  fact:       'bg-cyan-900/50 text-cyan-300 border-cyan-700/40',
  procedure:  'bg-amber-900/50 text-amber-300 border-amber-700/40',
  context:    'bg-zinc-800 text-zinc-300 border-zinc-700/40',
  domain:     'bg-indigo-900/50 text-indigo-300 border-indigo-700/40',
  rule:       'bg-rose-900/50 text-rose-300 border-rose-700/40',
  insight:    'bg-teal-900/50 text-teal-300 border-teal-700/40',
}

// ── fetch ────────────────────────────────────────────────────────────────────
async function fetchPage(p: number, append = false) {
  if (append) appending.value = true
  else loading.value = true

  try {
    const params: Record<string, string | number> = { page: p }
    if (q.value.trim())       params.q    = q.value.trim()
    if (typeFilter.value)     params.type = typeFilter.value
    if (activeFilter.value !== 'all') params.active = activeFilter.value === 'active' ? 1 : 0
    if (sortBy.value === 'oldest')    params.sort = 'asc'
    if (sortBy.value === 'confidence') { params.sort_by = 'confidence'; params.sort = 'desc' }
    if (sortBy.value === 'usage')     { params.sort_by = 'usage_count'; params.sort = 'desc' }

    const url = apiUrl('/memory') + '?' + new URLSearchParams(
      Object.fromEntries(Object.entries(params).map(([k, v]) => [k, String(v)])),
    ).toString()

    const res = await $fetch<PaginatedResponse>(url)
    if (append) items.value.push(...(res.data ?? []))
    else        items.value = res.data ?? []
    total.value    = res.total ?? 0
    lastPage.value = res.last_page ?? 1
    page.value     = p
  }
  finally {
    loading.value   = false
    appending.value = false
  }
}

// debounced text search
let debounceTimer: ReturnType<typeof setTimeout>
watch([q, typeFilter, activeFilter, sortBy], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchPage(1), 300)
})

// initial load
await fetchPage(1)

function loadMore() {
  if (page.value < lastPage.value) fetchPage(page.value + 1, true)
}

// ── semantic search ───────────────────────────────────────────────────────────
async function runSemanticSearch() {
  if (!semanticQ.value.trim()) return
  semanticLoading.value = true
  semanticMode.value = true
  try {
    const res = await $fetch<MemoryItem[]>(apiUrl('/memory/search'), {
      method: 'POST',
      body: { query: semanticQ.value, top_k: 12 },
    })
    semanticResults.value = Array.isArray(res) ? res : []
  }
  finally {
    semanticLoading.value = false
  }
}

function clearSemantic() {
  semanticMode.value = false
  semanticResults.value = []
  semanticQ.value = ''
}

// ── actions ──────────────────────────────────────────────────────────────────
async function deleteMemory(id: string) {
  if (!confirm('Delete this memory?')) return
  deletingId.value = id
  try {
    await $fetch(apiUrl(`/memory/${id}`), { method: 'DELETE' })
    items.value = items.value.filter(m => m.id !== id)
    semanticResults.value = semanticResults.value.filter(m => m.id !== id)
    total.value = Math.max(0, total.value - 1)
  }
  finally {
    deletingId.value = null
  }
}

async function toggleActive(m: MemoryItem) {
  togglingId.value = m.id
  try {
    await $fetch(apiUrl(`/memory/${m.id}`), {
      method: 'PATCH',
      body: { is_active: !m.is_active },
    })
    m.is_active = !m.is_active
  }
  finally {
    togglingId.value = null
  }
}

// ── helpers ──────────────────────────────────────────────────────────────────
const displayList = computed(() => semanticMode.value ? semanticResults.value : items.value)

function typeClass(type?: string) {
  return type ? (TYPE_COLORS[type] ?? 'bg-zinc-800 text-zinc-400 border-zinc-700/40') : ''
}

function shortDate(iso?: string) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function preview(m: MemoryItem) {
  return m.human_summary || m.content || ''
}
</script>

<template>
  <div class="space-y-5">

    <!-- ── header stats ─────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <p class="text-sm text-zinc-400">
        <span class="font-semibold text-zinc-200">{{ total.toLocaleString() }}</span>
        {{ total === 1 ? 'memory' : 'memories' }} stored
        <span v-if="semanticMode" class="ml-2 text-violet-400">· semantic search active</span>
      </p>
      <button
        v-if="semanticMode"
        type="button"
        class="text-xs text-zinc-500 underline hover:text-zinc-300"
        @click="clearSemantic"
      >
        Clear semantic search
      </button>
    </div>

    <!-- ── search + filter bar ──────────────────────────────────────────── -->
    <div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-4">

      <!-- row 1: text search + semantic search -->
      <div class="flex flex-col gap-2 sm:flex-row">
        <!-- text filter (live) -->
        <div class="relative flex-1">
          <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
          </svg>
          <input
            v-model="q"
            class="w-full rounded-lg border border-zinc-700 bg-zinc-800/80 py-2 pl-9 pr-3 text-sm text-zinc-100 placeholder-zinc-500 focus:border-zinc-500 focus:outline-none"
            placeholder="Filter memories…"
          >
        </div>

        <!-- semantic search -->
        <form class="flex gap-2" @submit.prevent="runSemanticSearch">
          <input
            v-model="semanticQ"
            class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 focus:border-violet-500 focus:outline-none sm:w-52"
            placeholder="Semantic search…"
          >
          <button
            type="submit"
            :disabled="semanticLoading || !semanticQ.trim()"
            class="flex items-center gap-1.5 rounded-lg bg-violet-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-600 disabled:opacity-50"
          >
            <svg v-if="semanticLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <span>Vector</span>
          </button>
        </form>
      </div>

      <!-- row 2: filter chips + sort -->
      <div class="flex flex-wrap items-center gap-2">

        <!-- type filter -->
        <select
          v-model="typeFilter"
          class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs text-zinc-300 focus:border-zinc-500 focus:outline-none"
        >
          <option value="">All types</option>
          <option v-for="t in MEMORY_TYPES" :key="t" :value="t">{{ t }}</option>
        </select>

        <!-- active filter -->
        <div class="flex rounded-lg border border-zinc-700 bg-zinc-800 text-xs">
          <button
            v-for="opt in [{ v: 'all', label: 'All' }, { v: 'active', label: 'Active' }, { v: 'inactive', label: 'Inactive' }]"
            :key="opt.v"
            type="button"
            class="px-3 py-1.5 transition"
            :class="activeFilter === opt.v ? 'bg-zinc-600 text-zinc-100 rounded-md' : 'text-zinc-400 hover:text-zinc-200'"
            @click="activeFilter = opt.v as typeof activeFilter"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- sort -->
        <select
          v-model="sortBy"
          class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs text-zinc-300 focus:border-zinc-500 focus:outline-none"
        >
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
          <option value="confidence">Highest confidence</option>
          <option value="usage">Most used</option>
        </select>

        <!-- clear filters -->
        <button
          v-if="q || typeFilter || activeFilter !== 'all'"
          type="button"
          class="ml-auto text-xs text-zinc-500 underline hover:text-zinc-300"
          @click="q = ''; typeFilter = ''; activeFilter = 'all'"
        >
          Clear filters
        </button>
      </div>
    </div>

    <!-- ── memory list ───────────────────────────────────────────────────── -->
    <UiSkeleton v-if="loading" class="h-64 w-full" />

    <UiEmptyState
      v-else-if="displayList.length === 0"
      :title="semanticMode ? 'No semantic matches.' : 'No memories found.'"
      :hint="semanticMode ? 'Try a different query.' : 'Adjust your filters or run a prompt with memory storage enabled.'"
    />

    <ul v-else class="space-y-3">
      <li
        v-for="m in displayList"
        :key="m.id"
        class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-zinc-700"
        :class="{ 'opacity-50': m.is_active === false }"
      >
        <!-- card header -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <!-- type badge -->
            <span
              v-if="m.type"
              class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
              :class="typeClass(m.type)"
            >
              {{ m.type }}
            </span>

            <!-- confidence pill -->
            <MemoryConfidencePill v-if="m.confidence != null" :confidence="m.confidence" />

            <!-- inactive badge -->
            <span v-if="m.is_active === false" class="inline-flex items-center rounded-full border border-zinc-700/50 bg-zinc-800/80 px-2 py-0.5 text-xs text-zinc-500">
              inactive
            </span>
          </div>

          <!-- actions -->
          <div class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
            <!-- toggle active -->
            <button
              type="button"
              :title="m.is_active === false ? 'Mark active' : 'Mark inactive'"
              :disabled="togglingId === m.id"
              class="rounded-md p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-zinc-300 disabled:opacity-40"
              @click="toggleActive(m)"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </button>
            <!-- delete -->
            <button
              type="button"
              title="Delete"
              :disabled="deletingId === m.id"
              class="rounded-md p-1.5 text-zinc-500 transition hover:bg-rose-900/40 hover:text-rose-400 disabled:opacity-40"
              @click="deleteMemory(m.id)"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
            <!-- expand -->
            <button
              type="button"
              :title="expandedId === m.id ? 'Collapse' : 'Expand'"
              class="rounded-md p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-zinc-300"
              @click="expandedId = expandedId === m.id ? null : m.id"
            >
              <svg class="h-4 w-4 transition-transform" :class="expandedId === m.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- content preview -->
        <p
          class="mt-2 text-sm leading-relaxed text-zinc-200"
          :class="expandedId !== m.id ? 'line-clamp-2' : ''"
        >
          {{ preview(m) }}
        </p>

        <!-- expanded: full content if different from summary -->
        <div v-if="expandedId === m.id && m.human_summary && m.content && m.human_summary !== m.content" class="mt-3 rounded-lg border border-zinc-800 bg-zinc-950/60 p-3">
          <p class="mb-1 text-xs font-medium text-zinc-500">Raw content</p>
          <p class="text-xs leading-relaxed text-zinc-400">{{ m.content }}</p>
        </div>

        <!-- footer: tags + meta -->
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <!-- tags -->
          <span
            v-for="tag in (m.tags ?? [])"
            :key="tag"
            class="cursor-pointer rounded-full border border-zinc-700/50 bg-zinc-800/60 px-2 py-0.5 text-xs text-zinc-400 transition hover:border-zinc-500 hover:text-zinc-200"
            @click="typeFilter === tag ? typeFilter = '' : q = tag"
          >
            #{{ tag }}
          </span>

          <!-- spacer -->
          <span class="flex-1" />

          <!-- source -->
          <span v-if="m.source" class="text-xs text-zinc-600">{{ m.source }}</span>

          <!-- usage -->
          <span v-if="m.usage_count" class="text-xs text-zinc-600">
            used {{ m.usage_count }}×
          </span>

          <!-- date -->
          <span class="text-xs text-zinc-600">{{ shortDate(m.updated_at) }}</span>
        </div>
      </li>
    </ul>

    <!-- ── load more ────────────────────────────────────────────────────── -->
    <div v-if="!semanticMode && page < lastPage" class="flex justify-center pt-2">
      <button
        type="button"
        :disabled="appending"
        class="flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-5 py-2 text-sm text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100 disabled:opacity-50"
        @click="loadMore"
      >
        <svg v-if="appending" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
        </svg>
        Load more
      </button>
    </div>

    <!-- ── semantic search footer note ─────────────────────────────────── -->
    <p v-if="semanticMode" class="text-center text-xs text-zinc-600">
      Showing {{ semanticResults.length }} vector matches for "{{ semanticQ }}"
    </p>

  </div>
</template>
