<script setup lang="ts">
import type { WorkspaceFolderEntry } from '~/composables/useProjects'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  select: [folder: WorkspaceFolderEntry]
}>()

const registry = useProjects()

const currentRelative = ref('')
const folders = ref<WorkspaceFolderEntry[]>([])
const loading = ref(false)
const unavailable = ref(false)
const unavailableMessage = ref('')

const breadcrumbParts = computed(() => {
  if (!currentRelative.value) return []
  return currentRelative.value.split('/').filter(Boolean)
})

function breadcrumbPath(index: number): string {
  return breadcrumbParts.value.slice(0, index + 1).join('/')
}

async function loadFolders(relative = '') {
  loading.value = true
  unavailable.value = false
  unavailableMessage.value = ''
  try {
    const res = await registry.listWorkspaceFolders(relative)
    if (!res.available) {
      unavailable.value = true
      unavailableMessage.value = res.message ?? 'Workspace is not available.'
      folders.value = []
      currentRelative.value = relative
      return
    }
    currentRelative.value = res.path
    folders.value = res.folders
  }
  catch (e: unknown) {
    unavailable.value = true
    unavailableMessage.value = e instanceof Error ? e.message : 'Could not load workspace folders.'
    folders.value = []
  }
  finally {
    loading.value = false
  }
}

function openFolder(folder: WorkspaceFolderEntry) {
  void loadFolders(folder.relative)
}

function chooseFolder(folder: WorkspaceFolderEntry) {
  emit('select', folder)
}

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = isOpen ? 'hidden' : ''
    if (isOpen) {
      void loadFolders('')
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="workspace-folder-picker-title"
      data-testid="workspace-folder-picker"
      @click.self="emit('close')"
    >
      <div
        class="flex w-full max-w-lg max-h-[85vh] flex-col overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-2xl"
        @click.stop
      >
        <div class="shrink-0 border-b border-zinc-800 px-5 py-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 id="workspace-folder-picker-title" class="text-sm font-semibold text-zinc-100">
                Select project folder
              </h2>
              <p class="mt-1 text-xs text-zinc-500">
                Browse
                <code class="text-emerald-500">{{ registry.workspace?.workspace_mount ?? '/workspace' }}</code>
                (Docker workspace mount)
              </p>
            </div>
            <button
              type="button"
              class="shrink-0 rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-400 hover:text-zinc-200"
              @click="emit('close')"
            >
              Close
            </button>
          </div>
        </div>

        <div class="shrink-0 border-b border-zinc-800 px-5 py-2">
          <nav class="flex flex-wrap items-center gap-1 text-xs text-zinc-500" aria-label="Folder path">
            <button
              type="button"
              class="rounded px-1 py-0.5 hover:bg-zinc-800 hover:text-emerald-400"
              :class="currentRelative === '' ? 'text-emerald-400' : ''"
              @click="loadFolders('')"
            >
              {{ registry.workspace?.workspace_mount ?? '/workspace' }}
            </button>
            <template v-for="(part, index) in breadcrumbParts" :key="index">
              <span>/</span>
              <button
                type="button"
                class="rounded px-1 py-0.5 hover:bg-zinc-800 hover:text-emerald-400"
                :class="index === breadcrumbParts.length - 1 ? 'text-emerald-400' : ''"
                @click="loadFolders(breadcrumbPath(index))"
              >
                {{ part }}
              </button>
            </template>
          </nav>
        </div>

        <div class="min-h-0 flex-1 overflow-auto px-2 py-2">
          <p v-if="loading" class="px-3 py-4 text-sm text-zinc-500">
            Loading folders…
          </p>
          <div
            v-else-if="unavailable"
            class="mx-3 my-4 rounded-md border border-rose-900/60 bg-rose-950/30 px-3 py-3 text-sm text-rose-200"
          >
            {{ unavailableMessage }}
            <p class="mt-2 text-xs text-rose-200/70">
              Docker workspace mount missing. Check <code>../:/workspace</code> in docker-compose.yml and restart containers.
            </p>
          </div>
          <p v-else-if="folders.length === 0" class="px-3 py-4 text-sm text-zinc-500">
            No subfolders here. Select this folder using the button below.
          </p>
          <ul v-else class="space-y-0.5">
            <li
              v-for="folder in folders"
              :key="folder.path"
              class="flex items-center gap-1 rounded-md hover:bg-zinc-800/60"
            >
              <button
                type="button"
                class="min-w-0 flex-1 truncate px-3 py-2 text-left text-sm text-zinc-200"
                @click="openFolder(folder)"
              >
                <span class="text-zinc-500">{{ folder.has_children ? '▶' : '·' }}</span>
                {{ folder.name }}/
              </button>
              <button
                type="button"
                class="shrink-0 rounded-md border border-emerald-800 px-2 py-1 text-xs text-emerald-300 hover:bg-emerald-950"
                data-testid="workspace-folder-select"
                @click="chooseFolder(folder)"
              >
                Select
              </button>
            </li>
          </ul>
        </div>

        <div class="shrink-0 border-t border-zinc-800 px-5 py-3">
          <button
            type="button"
            class="w-full rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-50"
            :disabled="loading || unavailable"
            data-testid="workspace-folder-select-current"
            @click="chooseFolder({
              name: breadcrumbParts.at(-1) ?? registry.workspace?.workspace_mount?.split('/').pop() ?? 'workspace',
              path: `${registry.workspace?.workspace_mount ?? '/workspace'}${currentRelative ? `/${currentRelative}` : ''}`,
              relative: currentRelative,
              has_children: folders.length > 0,
            })"
          >
            Select current folder
            <span v-if="currentRelative" class="font-mono text-emerald-200/80">/{{ currentRelative }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
