<script setup lang="ts">
import type { AgentPersonaDetail, AgentPersonaListItem } from '~/composables/useAgentPersonas'

definePageMeta({ layout: 'default' })

const { list, get, save, reset } = useAgentPersonas()
const toast = useToast()

const {
  data: listData,
  pending: listPending,
  error: listError,
  refresh: refreshList,
} = await useAsyncData('agent-personas-list', () => list())

const roles = computed<AgentPersonaListItem[]>(() => listData.value ?? [])
const selectedRole = ref<string | null>(null)

watch(roles, (r) => {
  if (r.length && !selectedRole.value) {
    selectedRole.value = r[0].role
  }
}, { immediate: true })

const detail = ref<AgentPersonaDetail | null>(null)
const editedContent = ref('')
const editedEnabled = ref(true)
const savedContent = ref('')
const savedEnabled = ref(true)
const showBuiltin = ref(false)
const saving = ref(false)

const isDirty = computed(
  () => editedContent.value !== savedContent.value || editedEnabled.value !== savedEnabled.value,
)

async function loadDetail(role: string) {
  detail.value = await get(role)
  editedContent.value = detail.value.content ?? ''
  editedEnabled.value = detail.value.enabled
  savedContent.value = editedContent.value
  savedEnabled.value = editedEnabled.value
}

watch(selectedRole, async (role) => {
  if (role) {
    await loadDetail(role)
  }
}, { immediate: true })

function selectRole(role: string) {
  if (role === selectedRole.value) return
  selectedRole.value = role
}

onBeforeRouteLeave((to, from, next) => {
  if (!isDirty.value) {
    next()
    return
  }
  if (confirm('You have unsaved persona changes. Leave without saving?')) {
    next()
  }
  else {
    next(false)
  }
})

async function handleSave() {
  if (!selectedRole.value) return
  saving.value = true
  try {
    await save(selectedRole.value, { content: editedContent.value, enabled: editedEnabled.value })
    await Promise.all([refreshList(), loadDetail(selectedRole.value)])
    toast.success('Persona saved.')
  }
  catch {
    toast.error('Failed to save persona.')
  }
  finally {
    saving.value = false
  }
}

async function handleReset() {
  if (!selectedRole.value || !confirm('Reset this persona to default content?')) return
  saving.value = true
  try {
    await reset(selectedRole.value)
    await Promise.all([refreshList(), loadDetail(selectedRole.value)])
    toast.success('Persona reset to default.')
  }
  catch {
    toast.error('Failed to reset persona.')
  }
  finally {
    saving.value = false
  }
}

</script>

<template>
  <div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4">
      <div>
        <h1 class="text-xl font-semibold text-zinc-100">Personas</h1>
        <p class="text-sm text-zinc-400 mt-1">
          Saved personas apply to every new chat message and each agent step in a run (including revision loops).
        </p>
      </div>

      <div v-if="listPending" class="text-sm text-zinc-500">Loading roles…</div>
      <div
        v-else-if="listError"
        class="rounded-lg border border-rose-900/50 bg-rose-950/30 px-3 py-3 text-sm text-rose-300"
      >
        <p>Could not load personas. Check that the API is running.</p>
        <button
          type="button"
          class="mt-2 text-xs underline hover:text-rose-200"
          @click="refreshList()"
        >
          Retry
        </button>
      </div>
      <div v-else-if="roles.length === 0" class="rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-3 text-sm text-zinc-400">
        <p>No agent roles returned.</p>
        <button
          type="button"
          class="mt-2 text-xs text-emerald-400 hover:text-emerald-300"
          @click="refreshList()"
        >
          Reload
        </button>
      </div>
      <div v-else class="space-y-1">
        <button
          v-for="item in roles"
          :key="item.role"
          type="button"
          class="w-full text-left rounded-lg border px-3 py-2.5 transition-colors"
          :class="selectedRole === item.role
            ? 'border-emerald-700 bg-emerald-950/30 text-zinc-100'
            : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:border-zinc-700'"
          @click="selectRole(item.role)"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium">{{ item.display_name }}</span>
            <span
              class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded border"
              :class="item.enabled ? 'border-emerald-800 text-emerald-400' : 'border-zinc-700 text-zinc-500'"
            >
              {{ item.enabled ? 'on' : 'off' }}
            </span>
          </div>
          <p class="text-xs text-zinc-500 mt-1 font-mono">{{ item.role }}</p>
        </button>
      </div>
    </div>

    <div class="lg:col-span-2 space-y-4">
      <template v-if="detail && selectedRole">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-medium text-zinc-100">{{ detail.display_name }}</h2>
          <label class="flex items-center gap-2 text-sm text-zinc-400 cursor-pointer">
            <input v-model="editedEnabled" type="checkbox" class="rounded border-zinc-600 bg-zinc-800">
            Enabled
          </label>
        </div>

        <textarea
          v-model="editedContent"
          rows="18"
          class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-sm text-zinc-200 placeholder-zinc-600 focus:border-zinc-500 focus:outline-none resize-y"
          placeholder="Persona instructions (markdown)…"
        />

        <details class="rounded-lg border border-zinc-800 bg-zinc-950">
          <summary
            class="cursor-pointer px-4 py-2 text-xs text-zinc-400 hover:text-zinc-200"
            @click="showBuiltin = !showBuiltin"
          >
            Built-in system prompt (reference)
          </summary>
          <pre class="px-4 pb-4 text-xs text-zinc-500 whitespace-pre-wrap font-mono">{{ detail.builtin_preview }}</pre>
        </details>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
            :disabled="saving || !isDirty"
            @click="handleSave"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <span v-if="isDirty" class="text-xs text-amber-400/90">Unsaved changes</span>
          <button
            type="button"
            class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800 disabled:opacity-50"
            :disabled="saving"
            @click="handleReset"
          >
            Reset to default
          </button>
        </div>
      </template>
      <p v-else class="text-sm text-zinc-500">Select a role to edit its persona.</p>
    </div>
  </div>
</template>
