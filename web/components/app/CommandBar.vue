<script setup lang="ts">
export interface Command {
  id: string
  label: string
  action: () => void
}

const props = defineProps<{
  commands?: Command[]
}>()

const emit = defineEmits<{ close: [] }>()

const query = ref('')
const activeIndex = ref(0)

const filtered = computed(() => {
  const q = query.value.toLowerCase().trim()
  const cmds = props.commands ?? []
  if (!q) return cmds
  return cmds.filter(c => c.label.toLowerCase().includes(q))
})

watch(filtered, () => { activeIndex.value = 0 })

function select(cmd: Command) {
  cmd.action()
  emit('close')
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    const cmd = filtered.value[activeIndex.value]
    if (cmd) select(cmd)
  } else if (e.key === 'Escape') {
    emit('close')
  }
}

const inputRef = ref<HTMLInputElement | null>(null)
onMounted(() => nextTick(() => inputRef.value?.focus()))
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-start justify-center pt-24 bg-black/60"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-lg bg-zinc-900 border border-zinc-700 rounded-xl shadow-2xl overflow-hidden">
        <div class="flex items-center px-4 h-12 border-b border-zinc-700 gap-3">
          <span class="text-zinc-500 text-sm">⌘</span>
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            placeholder="Search commands..."
            class="flex-1 bg-transparent text-sm text-zinc-100 placeholder-zinc-500 outline-none"
            @keydown="onKeydown"
          >
          <span class="text-xs text-zinc-600">ESC to close</span>
        </div>
        <ul class="max-h-72 overflow-y-auto py-2">
          <li
            v-for="(cmd, i) in filtered"
            :key="cmd.id"
            class="px-4 py-2.5 text-sm cursor-pointer transition-colors"
            :class="i === activeIndex ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-300 hover:bg-zinc-800'"
            @click="select(cmd)"
            @mousemove="activeIndex = i"
          >
            {{ cmd.label }}
          </li>
          <li v-if="filtered.length === 0" class="px-4 py-4 text-sm text-zinc-500 text-center">
            No commands found.
          </li>
        </ul>
      </div>
    </div>
  </Teleport>
</template>
