<script setup lang="ts">
import { useResizableSidebarWidth } from '~/composables/useResizableSidebarWidth'

const { width, isResizing, startResize } = useResizableSidebarWidth()
</script>

<template>
  <aside
    data-testid="pixel-office-sidebar"
    class="relative hidden h-[min(calc(100vh-8rem),900px)] shrink-0 flex-col overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950 lg:flex"
    :class="isResizing ? 'select-none' : ''"
    :style="{ width: `${width}px` }"
  >
    <button
      type="button"
      data-testid="pixel-office-resize-handle"
      class="absolute left-0 top-0 z-10 h-full w-1.5 -translate-x-1/2 cursor-col-resize touch-none border-0 bg-transparent p-0 hover:bg-emerald-500/20 focus-visible:bg-emerald-500/30 focus-visible:outline-none"
      aria-label="Resize pixel office panel"
      @pointerdown="startResize"
    />
    <div class="flex min-h-0 flex-1 flex-col pl-1">
      <slot />
    </div>
  </aside>
</template>
