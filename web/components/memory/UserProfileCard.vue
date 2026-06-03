<script setup lang="ts">
const { profile, loading, saving, generating, error, load, save, generate } = useUserProfile()

const editing = ref(false)
const draftHeadline = ref('')
const draftContent = ref('')
const collapsed = ref(false)

const MANUAL_TEMPLATE = `## Who they are
(role, focus, expertise)

## What they're building
(products, companies, domains)

## Working posture & preferences
(how they want the assistant to behave)

## Operating standard
(instruction sources, e.g. AGENTS.md → CLAUDE.md → rules)`

onMounted(load)

function startEdit() {
  draftHeadline.value = profile.value?.headline ?? ''
  draftContent.value = profile.value?.content ?? MANUAL_TEMPLATE
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

async function saveEdit() {
  const ok = await save(draftContent.value, draftHeadline.value.trim() || null)
  if (ok) editing.value = false
}

async function regenerate() {
  await generate()
  if (!error.value) editing.value = false
}

function shortDate(iso?: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

const originLabel = computed(() => {
  if (profile.value?.origin === 'auto') return 'AI-generated'
  if (profile.value?.origin === 'manual') return 'Hand-written'
  return null
})
</script>

<template>
  <section class="rounded-xl border border-violet-800/40 bg-gradient-to-br from-violet-950/30 to-zinc-900/60 p-5">
    <!-- header -->
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-800/40 text-violet-300">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </span>
        <div>
          <h2 class="text-sm font-semibold text-zinc-100">Know your user</h2>
          <p class="text-xs text-zinc-500">What BosskuAI remembers about you across every session.</p>
        </div>
      </div>

      <div v-if="!editing" class="flex shrink-0 items-center gap-1">
        <button
          type="button"
          title="Regenerate from memory"
          :disabled="generating"
          class="flex items-center gap-1.5 rounded-md border border-zinc-700 bg-zinc-800/70 px-2.5 py-1.5 text-xs text-zinc-300 transition hover:border-violet-600 hover:text-violet-300 disabled:opacity-50"
          @click="regenerate"
        >
          <svg v-if="generating" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
          <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M5.5 9A7 7 0 0118.4 7.6M18.5 15A7 7 0 015.6 16.4" />
          </svg>
          <span>{{ generating ? 'Thinking…' : 'Regenerate' }}</span>
        </button>
        <button
          v-if="profile"
          type="button"
          title="Edit profile"
          class="rounded-md p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-zinc-200"
          @click="startEdit"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          v-if="profile"
          type="button"
          :title="collapsed ? 'Expand' : 'Collapse'"
          class="rounded-md p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-zinc-200"
          @click="collapsed = !collapsed"
        >
          <svg class="h-4 w-4 transition-transform" :class="collapsed ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- error -->
    <p v-if="error" class="mt-3 rounded-lg border border-rose-800/50 bg-rose-950/30 px-3 py-2 text-xs text-rose-300">
      {{ error }}
    </p>

    <!-- loading -->
    <UiSkeleton v-if="loading" class="mt-4 h-24 w-full" />

    <!-- edit mode -->
    <div v-else-if="editing" class="mt-4 space-y-3">
      <div>
        <label class="mb-1 block text-xs font-medium text-zinc-400">Headline</label>
        <input
          v-model="draftHeadline"
          class="w-full rounded-lg border border-zinc-700 bg-zinc-800/80 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 focus:border-violet-500 focus:outline-none"
          placeholder="One-line summary of who you are…"
        >
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-zinc-400">Profile</label>
        <textarea
          v-model="draftContent"
          rows="12"
          class="w-full resize-y rounded-lg border border-zinc-700 bg-zinc-800/80 px-3 py-2 font-mono text-xs leading-relaxed text-zinc-100 placeholder-zinc-500 focus:border-violet-500 focus:outline-none"
          placeholder="Markdown describing the user…"
        />
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          :disabled="saving || !draftContent.trim()"
          class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-600 disabled:opacity-50"
          @click="saveEdit"
        >
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 transition hover:border-zinc-500"
          @click="cancelEdit"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- empty state -->
    <div v-else-if="!profile" class="mt-4 rounded-lg border border-dashed border-zinc-700/70 bg-zinc-900/40 p-5 text-center">
      <p class="text-sm text-zinc-400">No profile yet.</p>
      <p class="mt-1 text-xs text-zinc-500">
        Let BosskuAI synthesise one from your stored memories, or write it yourself.
      </p>
      <div class="mt-3 flex items-center justify-center gap-2">
        <button
          type="button"
          :disabled="generating"
          class="flex items-center gap-1.5 rounded-lg bg-violet-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-600 disabled:opacity-50"
          @click="regenerate"
        >
          <svg v-if="generating" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
          {{ generating ? 'Generating…' : 'Generate from memory' }}
        </button>
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 transition hover:border-zinc-500"
          @click="startEdit"
        >
          Write manually
        </button>
      </div>
    </div>

    <!-- display mode -->
    <div v-else-if="!collapsed" class="mt-4 space-y-3">
      <p v-if="profile.headline" class="text-sm font-medium leading-relaxed text-zinc-100">
        {{ profile.headline }}
      </p>
      <p class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-300">{{ profile.content }}</p>

      <div class="flex flex-wrap items-center gap-2 pt-1 text-xs text-zinc-600">
        <span
          v-if="originLabel"
          class="inline-flex items-center rounded-full border px-2 py-0.5 font-medium"
          :class="profile.origin === 'auto' ? 'border-violet-700/40 bg-violet-900/40 text-violet-300' : 'border-zinc-700/50 bg-zinc-800/60 text-zinc-400'"
        >
          {{ originLabel }}
        </span>
        <span v-if="profile.generated_by_model" class="text-zinc-600">{{ profile.generated_by_model }}</span>
        <span class="flex-1" />
        <span v-if="profile.updated_at">updated {{ shortDate(profile.updated_at) }}</span>
      </div>
    </div>
  </section>
</template>
