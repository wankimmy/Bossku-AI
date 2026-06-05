<script setup lang="ts">
import type { ProjectTreeEntry } from '~/composables/useProjectTree'

definePageMeta({ layout: 'default' })

const { fetchTree } = useProjectTree()
const { search } = useProjectSearch()
const changes = useProjectChanges()
const registry = useProjects()
const toast = useToast()
const router = useRouter()

const pathsPanelOpen = ref(true)
const newProjectName = ref('')
const newProjectHostPath = ref('')
const hostPathInput = ref<HTMLInputElement | null>(null)
const registerLoading = ref(false)
const activateLoading = ref<string | null>(null)

const repoRoot = ref('')
const currentPath = ref('')
/** Directory path ("" = root) → immediate children from /project/tree */
const treeChildrenByPath = ref<Record<string, ProjectTreeEntry[]>>({})
const expandedDirs = ref<Record<string, boolean>>({})
const treeLoading = ref(false)
const treeLoadingPath = ref<string | null>(null)

const searchQuery = ref('')
const searchResults = ref<{ path: string; line: number; preview: string }[]>([])
const searchLoading = ref(false)

const selectedPath = ref('')
const fileContents = ref('')
const originalContents = ref('')
const editing = ref(false)
const fileLoading = ref(false)

const mobileTab = ref<'tree' | 'file' | 'changes'>('tree')
const api = useApi()

type VisibleTreeRow = {
  entry: ProjectTreeEntry
  depth: number
}

/** Flattened rows for the sidebar — keeps reactivity in this page (no recursive child). */
const visibleTreeRows = computed((): VisibleTreeRow[] => {
  const rows: VisibleTreeRow[] = []
  const children = treeChildrenByPath.value
  const expanded = expandedDirs.value

  const walk = (dirPath: string, depth: number) => {
    for (const entry of children[dirPath] ?? []) {
      rows.push({ entry, depth })
      if (entry.type === 'dir' && expanded[entry.path]) {
        walk(entry.path, depth + 1)
      }
    }
  }

  walk('', 0)
  return rows
})

async function loadRoot() {
  try {
    const root = await api.get<{
      root: string
      relative: string
      available?: boolean
      error?: string | null
      active_project?: { id: string; name: string; host_path: string } | null
    }>('/project')
    repoRoot.value = root.root
    if (root.available === false && root.error) {
      toast.error(root.error)
    }
  }
  catch {
    repoRoot.value = ''
  }
}

const hostPathWarning = computed(() => {
  const path = newProjectHostPath.value.trim()
  if (!path) return ''
  const workspacePrefix = registry.workspace.value?.workspace_host_prefix?.trim() ?? ''
  if (!workspacePrefix) return ''
  if (!registry.hostUnderWorkspace(path)) {
    return 'Outside the mounted workspace. Edit docker-compose.yml to add another bind mount, then run docker compose up -d.'
  }
  return ''
})

const activeProject = computed(() => registry.projects.value.find(project => project.is_active) ?? null)

async function reloadProjectWorkspace() {
  expandedDirs.value = {}
  treeChildrenByPath.value = {}
  currentPath.value = ''
  selectedPath.value = ''
  fileContents.value = ''
  originalContents.value = ''
  editing.value = false
  searchResults.value = []
  searchQuery.value = ''

  await registry.refresh()
  await loadRoot()
  await loadTree('')
  await changes.refresh()
  mobileTab.value = 'tree'
}

function runProjectUnderstanding() {
  router.push({ path: '/', query: { insert: 'project-understanding' } })
}

async function submitRegister() {
  if (!newProjectName.value.trim() || !newProjectHostPath.value.trim()) return
  registerLoading.value = true
  try {
    const res = await registry.register(newProjectName.value.trim(), newProjectHostPath.value.trim())
    newProjectName.value = ''
    newProjectHostPath.value = ''
    if (res.project.is_active) {
      await reloadProjectWorkspace()
      toast.success(res.created ? 'Project registered and set as active.' : 'Project updated and active.')
    }
    else {
      await registry.refresh()
      toast.success(res.created ? 'Project registered. Click Activate to use it for runs and the file tree.' : 'Project updated.')
    }
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Could not register path')
  }
  finally {
    registerLoading.value = false
  }
}

async function openFolderHelper() {
  const bridge = (window as unknown as {
    bossku?: { openFolder?: () => Promise<string | null> }
  }).bossku
  if (bridge?.openFolder) {
    try {
      const folder = await bridge.openFolder()
      if (!folder) return
      newProjectHostPath.value = folder
      if (!newProjectName.value.trim()) {
        const parts = folder.replace(/[\\/]+$/, '').split(/[\\/]/)
        newProjectName.value = parts[parts.length - 1] || ''
      }
    }
    catch {
      toast.error('Could not open the folder picker.')
    }
    return
  }
  // Plain browser (not the desktop shell): no native picker available.
  toast.info('The native folder picker needs the BosskuAI desktop app. Paste the folder path here instead.')
  hostPathInput.value?.focus()
}

async function activateProject(id: string) {
  activateLoading.value = id
  try {
    const res = await registry.activate(id)
    await reloadProjectWorkspace()
    if (!res.available && res.error) {
      toast.error(res.error)
    }
    else {
      const indexed = res.manifest_total != null ? ` (${res.manifest_total} files indexed)` : ''
      toast.success(`Active project: ${res.project.name}${indexed}`)
    }
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Could not activate project')
  }
  finally {
    activateLoading.value = null
  }
}

async function loadTree(path = '') {
  const isRoot = path === ''
  if (isRoot) {
    treeLoading.value = true
  }
  else {
    treeLoadingPath.value = path
  }
  try {
    const res = await fetchTree(path)
    const key = (res.path ?? path).replace(/\\/g, '/')
    const requestKey = path.replace(/\\/g, '/')
    const next: Record<string, ProjectTreeEntry[]> = {
      ...treeChildrenByPath.value,
      [key]: res.entries,
    }
    if (requestKey !== '' && requestKey !== key) {
      next[requestKey] = res.entries
    }
    treeChildrenByPath.value = next
    if (isRoot || path === currentPath.value) {
      currentPath.value = key
    }
    return res.entries
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : `Could not load folder: ${path || '/'}`)
    throw e
  }
  finally {
    if (isRoot) {
      treeLoading.value = false
    }
    else if (treeLoadingPath.value === path) {
      treeLoadingPath.value = null
    }
  }
}

async function openDir(path: string) {
  currentPath.value = path
  await loadTree(path)
  mobileTab.value = 'file'
}

async function toggleDir(entry: ProjectTreeEntry) {
  if (expandedDirs.value[entry.path]) {
    const next = { ...expandedDirs.value }
    delete next[entry.path]
    expandedDirs.value = next
    return
  }
  if (!treeChildrenByPath.value[entry.path]?.length) {
    await loadTree(entry.path)
  }
  expandedDirs.value = { ...expandedDirs.value, [entry.path]: true }
}

async function openFile(path: string) {
  selectedPath.value = path
  fileLoading.value = true
  editing.value = false
  mobileTab.value = 'file'
  try {
    const res = await api.get<{ path: string; contents: string }>('/project/file', { path })
    fileContents.value = res.contents
    originalContents.value = res.contents
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Could not load file')
    fileContents.value = ''
    originalContents.value = ''
  }
  finally {
    fileLoading.value = false
  }
}

async function runSearch() {
  if (!searchQuery.value.trim()) return
  searchLoading.value = true
  try {
    const res = await search(searchQuery.value)
    searchResults.value = res.matches
  }
  finally {
    searchLoading.value = false
  }
}

async function proposeChange() {
  if (!selectedPath.value) return
  try {
    await changes.propose(selectedPath.value, fileContents.value)
    toast.success('Change proposed — approve in the Changes panel.')
    originalContents.value = fileContents.value
    editing.value = false
    mobileTab.value = 'changes'
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Failed to propose change')
  }
}

async function approveAndApply(id: string) {
  try {
    await changes.approve(id)
    await changes.apply(id)
    toast.success('Change applied.')
    if (selectedPath.value) await openFile(selectedPath.value)
  }
  catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Failed to apply change')
  }
}

const isDirty = computed(() => fileContents.value !== originalContents.value)

onMounted(() => {
  reloadProjectWorkspace()
})
</script>

<template>
  <div class="space-y-4">
    <header class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
      <h1 class="text-lg font-semibold text-zinc-100">
        Project
      </h1>
      <p class="mt-1 text-sm text-zinc-400">
        Browse and edit files under the active project folder
        <code class="rounded bg-zinc-950 px-1 text-xs text-emerald-400">{{ repoRoot || '(no folder selected)' }}</code>.
        Agent runs use this same root — add a folder, then click <strong class="text-zinc-300">Activate</strong> before auditing another project.
      </p>
    </header>

    <section
      v-if="activeProject"
      class="rounded-lg border border-emerald-900/60 bg-emerald-950/30 p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-emerald-200">
            Active project ready
          </p>
          <p class="text-sm text-emerald-100/80">
            Run <code class="rounded bg-zinc-950 px-1 text-emerald-300">/project-understanding</code> first so BosskuAI can map the repo structure, rules, skills, and workflow before deeper work.
          </p>
          <p class="text-xs text-emerald-200/70">
            Current active repo: <code class="rounded bg-zinc-950 px-1 text-emerald-300">{{ activeProject.name }}</code>
          </p>
        </div>
        <button
          type="button"
          class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600"
          @click="runProjectUnderstanding"
        >
          Run /project-understanding
        </button>
      </div>
    </section>

    <div
      v-if="registry.projects.length > 0 && !registry.activeProjectId"
      class="rounded-lg border border-amber-800/60 bg-amber-950/30 px-4 py-3 text-sm text-amber-200"
    >
      No active project. Security audits and the executor will fall back to the BosskuAI app's own folder until you click <strong>Activate</strong> on a project below.
    </div>

    <section class="rounded-lg border border-zinc-800 bg-zinc-900">
      <button
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-zinc-200"
        @click="pathsPanelOpen = !pathsPanelOpen"
      >
        <span>Project paths</span>
        <span class="text-zinc-500">{{ pathsPanelOpen ? '−' : '+' }}</span>
      </button>
      <div v-show="pathsPanelOpen" class="space-y-4 border-t border-zinc-800 px-4 pb-4 pt-3">
        <p v-if="registry.workspace?.workspace_host_prefix" class="text-xs text-zinc-500">
          Workspace:
          <code class="text-emerald-500">{{ registry.workspace.workspace_host_prefix }}</code>
          →
          <code class="text-emerald-500">{{ registry.workspace.workspace_mount }}</code>
        </p>
        <ul v-if="registry.projects.length" class="space-y-2 text-sm">
          <li
            v-for="proj in registry.projects"
            :key="proj.id"
            class="flex flex-wrap items-center gap-2 rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2"
          >
            <div class="min-w-0 flex-1">
              <div class="font-medium text-zinc-200">
                {{ proj.name }}
                <span v-if="proj.is_active" class="ml-1 text-xs text-emerald-500">(active)</span>
              </div>
              <div class="truncate font-mono text-xs text-zinc-500">
                {{ proj.host_path }}
              </div>
            </div>
            <button
              v-if="!proj.is_active"
              type="button"
              class="rounded-md bg-emerald-800 px-2 py-1 text-xs text-white disabled:opacity-50"
              :disabled="activateLoading === proj.id"
              @click="activateProject(proj.id)"
            >
              Activate
            </button>
            <button
              v-if="!proj.is_active"
              type="button"
              class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-400 hover:text-red-400"
              @click="registry.remove(proj.id)"
            >
              Remove
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-zinc-500">
          No project paths registered yet.
        </p>
        <form class="grid gap-2 sm:grid-cols-2" @submit.prevent="submitRegister">
          <input
            v-model="newProjectName"
            type="text"
            placeholder="Name (e.g. my-app)"
            class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm"
          >
          <input
            ref="hostPathInput"
            v-model="newProjectHostPath"
            type="text"
            placeholder="Folder path (e.g. C:/dev/projects/my-app)"
            class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm font-mono"
          >
          <p v-if="hostPathWarning" class="sm:col-span-2 text-xs text-amber-400">
            {{ hostPathWarning }}
          </p>
          <div class="sm:col-span-2 flex flex-wrap gap-2">
            <button
              type="submit"
              class="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white disabled:opacity-50"
              :disabled="registerLoading || !!hostPathWarning"
            >
              Add path
            </button>
            <button
              type="button"
              class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-800"
              @click="openFolderHelper"
            >
              Open Folder
            </button>
          </div>
          <p class="sm:col-span-2 text-xs text-zinc-500">
            Click <strong class="text-zinc-300">Open Folder</strong> to pick a folder with the native Windows dialog, or paste a path. (In a plain browser, paste the path manually.)
          </p>
        </form>
      </div>
    </section>

    <div class="flex gap-1 rounded-lg bg-zinc-900 p-1 lg:hidden">
      <button
        v-for="tab in ['tree', 'file', 'changes'] as const"
        :key="tab"
        type="button"
        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium capitalize"
        :class="mobileTab === tab ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-500'"
        @click="mobileTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)_340px]">
      <!-- Tree + search -->
      <aside
        class="space-y-3 rounded-lg border border-zinc-800 bg-zinc-900 p-3"
        :class="mobileTab !== 'tree' ? 'hidden lg:block' : ''"
      >
        <div class="flex gap-2">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search in repo…"
            class="min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm"
            @keydown.enter="runSearch"
          >
          <button
            type="button"
            class="shrink-0 rounded-md bg-emerald-700 px-2 py-1.5 text-xs text-white disabled:opacity-50"
            :disabled="searchLoading"
            @click="runSearch"
          >
            Search
          </button>
        </div>

        <ul v-if="searchResults.length" class="max-h-40 space-y-1 overflow-auto text-xs">
          <li v-for="hit in searchResults" :key="hit.path + hit.line">
            <button
              type="button"
              class="w-full rounded px-1 py-0.5 text-left text-zinc-300 hover:bg-zinc-800"
              @click="openFile(hit.path)"
            >
              <span class="font-mono text-emerald-500">{{ hit.path }}</span>
              <span class="text-zinc-500">:{{ hit.line }}</span>
            </button>
          </li>
        </ul>

        <div class="flex items-center justify-between text-xs text-zinc-500">
          <span class="truncate font-mono">{{ currentPath || '/' }}</span>
          <button
            v-if="currentPath"
            type="button"
            class="text-emerald-500 hover:underline"
            @click="loadTree('')"
          >
            Root
          </button>
        </div>

        <div v-if="treeLoading && !(treeChildrenByPath['']?.length)" class="text-xs text-zinc-500">
          Loading…
        </div>
        <div v-else class="max-h-[50vh] overflow-auto text-sm">
          <p v-if="!(treeChildrenByPath['']?.length)" class="px-2 text-xs text-zinc-500">
            No files in this folder.
          </p>
          <ul v-else class="space-y-0.5">
            <li
              v-for="{ entry, depth } in visibleTreeRows"
              :key="entry.path"
              :style="{ paddingLeft: `${depth * 12}px` }"
            >
              <button
                v-if="entry.type === 'dir'"
                type="button"
                class="flex w-full items-center gap-1 rounded px-2 py-1 text-left text-zinc-300 hover:bg-zinc-800"
                @click="toggleDir(entry)"
              >
                <span class="w-3 shrink-0 text-center text-[10px] text-zinc-500">
                  <span v-if="treeLoadingPath === entry.path">…</span>
                  <span v-else>{{ expandedDirs[entry.path] ? '▼' : '▶' }}</span>
                </span>
                <span class="truncate">{{ entry.name }}/</span>
              </button>
              <button
                v-else
                type="button"
                class="w-full truncate rounded px-2 py-1 text-left font-mono text-xs text-zinc-400 hover:bg-zinc-800"
                :class="selectedPath === entry.path ? 'bg-zinc-800 text-emerald-400' : ''"
                @click="openFile(entry.path)"
              >
                {{ entry.name }}
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <!-- File viewer -->
      <main
        class="rounded-lg border border-zinc-800 bg-zinc-900 p-3"
        :class="mobileTab !== 'file' ? 'hidden lg:block' : ''"
      >
        <div v-if="!selectedPath" class="py-16 text-center text-sm text-zinc-500">
          Select a file from the tree or search results.
        </div>
        <template v-else>
          <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="truncate font-mono text-xs text-emerald-400">{{ selectedPath }}</span>
            <button
              type="button"
              class="rounded border border-zinc-700 px-2 py-1 text-xs"
              @click="editing = !editing"
            >
              {{ editing ? 'View' : 'Edit' }}
            </button>
            <button
              type="button"
              class="rounded bg-emerald-700 px-3 py-1 text-xs text-white disabled:opacity-40"
              :disabled="!isDirty"
              @click="proposeChange"
            >
              Propose change
            </button>
          </div>
          <div v-if="fileLoading" class="text-sm text-zinc-500">
            Loading file…
          </div>
          <textarea
            v-else-if="editing"
            v-model="fileContents"
            class="min-h-[420px] w-full rounded-md border border-zinc-700 bg-zinc-950 p-3 font-mono text-xs leading-relaxed"
          />
          <pre
            v-else
            class="max-h-[60vh] overflow-auto rounded-md border border-zinc-800 bg-zinc-950 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap"
          >{{ fileContents }}</pre>
        </template>
      </main>

      <!-- Pending changes -->
      <aside
        class="space-y-3 rounded-lg border border-zinc-800 bg-zinc-900 p-3"
        :class="mobileTab !== 'changes' ? 'hidden lg:block' : ''"
      >
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-zinc-200">
            Pending changes
          </h2>
          <button type="button" class="text-xs text-zinc-500 hover:text-zinc-300" @click="changes.refresh()">
            Refresh
          </button>
        </div>
        <p v-if="changes.error.value" class="text-xs text-rose-400">
          {{ changes.error.value }}
        </p>
        <p v-else-if="changes.loading.value" class="text-xs text-zinc-500">
          Loading…
        </p>
        <p v-else-if="changes.pending.value.length === 0" class="text-xs text-zinc-500">
          No pending file changes.
        </p>
        <div v-for="item in changes.pending.value" :key="item.id" class="space-y-2 rounded-md border border-zinc-800 p-2">
          <p class="truncate font-mono text-xs text-emerald-400">
            {{ item.evidence?.path || item.operation_description }}
          </p>
          <p class="text-xs text-zinc-500">
            Risk: {{ item.risk_level }} · {{ item.status }}
          </p>
          <FileDiffViewer
            :path="item.evidence?.path"
            :change-type="item.evidence?.change_type"
            :diff="item.evidence?.diff"
            :after="item.evidence?.after"
            :before="item.evidence?.before"
          />
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded bg-emerald-700 px-2 py-1 text-xs text-white"
              @click="approveAndApply(item.id)"
            >
              Approve &amp; apply
            </button>
            <button
              type="button"
              class="rounded border border-rose-800 px-2 py-1 text-xs text-rose-300"
              @click="changes.reject(item.id)"
            >
              Reject
            </button>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>
