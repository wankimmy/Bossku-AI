<script setup lang="ts">
const { state, close } = useRunCommandPopup()
const copied = ref(false)

watch(
  () => state.open,
  (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = isOpen ? 'hidden' : ''
    if (isOpen) copied.value = false
  },
  { immediate: true },
)

onUnmounted(() => {
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})

async function copy() {
  try {
    await navigator.clipboard.writeText(state.command)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  }
  catch {
    /* clipboard not available */
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="state.open"
      class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="run-command-popup-title"
      @click.self="close"
    >
      <div
        class="w-full max-w-lg rounded-xl border border-zinc-700 bg-zinc-900 shadow-2xl"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-start gap-3 border-b border-zinc-700 px-5 py-4">
          <span class="mt-0.5 text-lg text-emerald-400" aria-hidden="true">$</span>
          <div class="min-w-0 flex-1">
            <h2 id="run-command-popup-title" class="text-sm font-semibold text-zinc-100">
              {{ state.title }}
            </h2>
            <p class="mt-1 text-xs leading-relaxed text-zinc-400">
              {{ state.description || 'BosskuAI cannot run this command directly. Copy it and run it in your terminal.' }}
            </p>
          </div>
          <button
            type="button"
            class="ml-2 shrink-0 rounded p-1 text-zinc-500 hover:text-zinc-200"
            aria-label="Close"
            @click="close"
          >
            ✕
          </button>
        </div>

        <!-- Command block -->
        <div class="px-5 py-4">
          <div class="relative rounded-md border border-zinc-700 bg-zinc-950">
            <pre class="overflow-x-auto px-4 py-3 text-xs font-mono leading-relaxed text-emerald-200 select-all">{{ state.command }}</pre>
            <button
              type="button"
              class="absolute right-2 top-2 rounded border border-zinc-700 bg-zinc-800 px-2 py-1 text-[11px] font-medium text-zinc-300 hover:bg-zinc-700"
              @click="copy"
            >
              {{ copied ? 'Copied!' : 'Copy' }}
            </button>
          </div>
          <p class="mt-2 text-[11px] text-zinc-500">
            Tip: select all text in the box above or press <kbd class="rounded bg-zinc-800 px-1 py-0.5 text-zinc-400">Copy</kbd> to copy to clipboard.
          </p>
        </div>

        <!-- Footer -->
        <div class="flex justify-end border-t border-zinc-700 px-5 py-3">
          <button
            type="button"
            class="rounded-lg bg-zinc-700 px-4 py-2 text-sm font-medium text-zinc-100 hover:bg-zinc-600"
            @click="close"
          >
            Done
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
