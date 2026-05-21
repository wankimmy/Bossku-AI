<script setup lang="ts">
const { toasts, remove } = useToast()

const icons: Record<string, string> = {
  success: '✓',
  error: '✕',
  warning: '⚠',
  info: 'ℹ',
}

const styles: Record<string, string> = {
  success: 'bg-emerald-950 border-emerald-700 text-emerald-200',
  error:   'bg-rose-950 border-rose-700 text-rose-200',
  warning: 'bg-amber-950 border-amber-700 text-amber-200',
  info:    'bg-zinc-900 border-zinc-700 text-zinc-200',
}

const iconStyles: Record<string, string> = {
  success: 'text-emerald-400',
  error:   'text-rose-400',
  warning: 'text-amber-400',
  info:    'text-zinc-400',
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2 w-80 pointer-events-none">
      <TransitionGroup
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-2 scale-95"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
          :class="styles[toast.type]"
        >
          <span class="mt-0.5 text-sm font-bold shrink-0" :class="iconStyles[toast.type]">
            {{ icons[toast.type] }}
          </span>
          <p class="flex-1 text-sm leading-snug">{{ toast.message }}</p>
          <button
            type="button"
            class="shrink-0 opacity-50 hover:opacity-100 text-xs ml-1"
            @click="remove(toast.id)"
          >
            ✕
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
