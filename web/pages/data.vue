<script setup lang="ts">
import type { DataTableMeta } from '~/composables/useDataExplorer'

definePageMeta({ layout: 'default' })

const { tables, rows, row } = useDataExplorer()

const tableList = ref<DataTableMeta[]>([])
const selectedTable = ref('')
const page = ref(1)
const perPage = 25
const search = ref('')
const rowData = ref<{ rows?: Record<string, unknown>[]; total?: number; primary_key?: string } | null>(null)
const pending = ref(false)
const drawerOpen = ref(false)
const drawerRow = ref<Record<string, unknown> | null>(null)
const drawerId = ref('')

async function loadTables() {
  tableList.value = await tables()
  if (!selectedTable.value && tableList.value.length) {
    selectedTable.value = tableList.value[0].name
  }
}

async function loadRows() {
  if (!selectedTable.value) return
  pending.value = true
  try {
    rowData.value = await rows(selectedTable.value, {
      page: page.value,
      per_page: perPage,
      search: search.value || undefined,
    }) as typeof rowData.value
  }
  finally {
    pending.value = false
  }
}

async function openRow(id: string) {
  drawerId.value = id
  drawerRow.value = await row(selectedTable.value, id) as Record<string, unknown>
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
  drawerRow.value = null
}

onMounted(() => {
  loadTables()
})

watch(selectedTable, () => {
  page.value = 1
  loadRows()
})

watch([page, search], () => {
  loadRows()
})

const columns = computed(() => {
  const t = tableList.value.find(x => x.name === selectedTable.value)
  return t?.columns?.map(c => c.name) ?? []
})

const totalPages = computed(() => {
  const total = rowData.value?.total ?? 0
  return Math.max(1, Math.ceil(total / perPage))
})

function cellPreview(val: unknown): string {
  if (val === null || val === undefined) return '—'
  const s = String(val)
  return s.length > 80 ? `${s.slice(0, 80)}…` : s
}

const pk = computed(() => rowData.value?.primary_key ?? 'id')
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-xl font-bold text-zinc-100">Data Explorer</h1>
      <p class="text-sm text-amber-600/90 mt-1">
        Read-only view of BosskuAI data. Secrets are masked.
      </p>
    </div>

    <div class="flex flex-wrap gap-3 items-center">
      <select
        v-model="selectedTable"
        class="bg-zinc-800 border border-zinc-700 text-sm text-zinc-100 rounded px-3 py-2 outline-none min-w-[200px]"
      >
        <option v-for="t in tableList" :key="t.name" :value="t.name">
          {{ t.label }} ({{ t.row_count }})
        </option>
      </select>
      <input
        v-model="search"
        type="text"
        placeholder="Search…"
        class="bg-zinc-800 border border-zinc-700 text-sm text-zinc-100 rounded px-3 py-2 outline-none placeholder-zinc-600"
      >
    </div>

    <div v-if="pending" class="text-sm text-zinc-500">Loading…</div>

    <div v-else class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-zinc-800">
              <th
                v-for="col in columns.slice(0, 8)"
                :key="col"
                class="px-3 py-2 text-left text-zinc-500 font-medium whitespace-nowrap"
              >
                {{ col }}
              </th>
              <th class="px-3 py-2 text-left text-zinc-500">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!(rowData?.rows?.length)" class="border-b border-zinc-800/50">
              <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-zinc-500">No rows.</td>
            </tr>
            <tr
              v-for="(r, idx) in rowData?.rows ?? []"
              :key="idx"
              class="border-b border-zinc-800/50 hover:bg-zinc-800/40"
            >
              <td
                v-for="col in columns.slice(0, 8)"
                :key="col"
                class="px-3 py-2 text-zinc-300 font-mono max-w-[200px] truncate"
              >
                {{ cellPreview(r[col]) }}
              </td>
              <td class="px-3 py-2">
                <button
                  type="button"
                  class="text-emerald-400 hover:text-emerald-300 text-xs"
                  @click="openRow(String(r[pk]))"
                >
                  View
                </button>
                <NuxtLink
                  v-if="r._links && (r._links as Record<string, string>).run"
                  :to="(r._links as Record<string, string>).run"
                  class="ml-2 text-cyan-400 hover:text-cyan-300 text-xs"
                >
                  Run
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex items-center gap-3 text-sm text-zinc-400">
      <button
        type="button"
        class="px-2 py-1 rounded border border-zinc-700 disabled:opacity-40"
        :disabled="page <= 1"
        @click="page--"
      >
        Prev
      </button>
      <span>Page {{ page }} / {{ totalPages }}</span>
      <button
        type="button"
        class="px-2 py-1 rounded border border-zinc-700 disabled:opacity-40"
        :disabled="page >= totalPages"
        @click="page++"
      >
        Next
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="drawerOpen && drawerRow"
        class="fixed inset-0 z-50 flex justify-end"
      >
        <div class="absolute inset-0 bg-black/60" @click="closeDrawer" />
        <aside class="relative w-full max-w-lg h-full bg-zinc-950 border-l border-zinc-800 overflow-y-auto p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-zinc-100">Row {{ drawerId }}</h2>
            <button type="button" class="text-zinc-400 hover:text-zinc-100" @click="closeDrawer">✕</button>
          </div>
          <dl class="space-y-3 text-xs">
            <div v-for="(val, key) in drawerRow" :key="String(key)">
              <template v-if="key !== '_links'">
                <dt class="text-zinc-500 font-mono mb-0.5">{{ key }}</dt>
                <dd class="text-zinc-200 whitespace-pre-wrap font-mono break-all">{{ val }}</dd>
              </template>
            </div>
          </dl>
          <div v-if="drawerRow._links" class="mt-4 flex gap-2">
            <NuxtLink
              v-for="(path, label) in (drawerRow._links as Record<string, string>)"
              :key="label"
              :to="path"
              class="text-xs text-cyan-400 hover:underline"
            >
              Open {{ label }}
            </NuxtLink>
          </div>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
