<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { SseEvent } from '~/composables/useRunStream'
import type { HandoffNode } from '~/types/bossku'
import {
  applyBosskuEventsToPixelOffice,
  createPixelOfficeAdapterState,
  resetPixelOfficeAdapterState,
  spawnCastMessages,
  type PixelOfficeHostMessage,
} from '~/utils/pixelOfficeAdapter'
import { loadAllPixelOfficeAssets } from '~/utils/pixelOfficeAssetLoader'
import {
  resolveOfficeLayout,
  savePersistedLayout,
  savePersistedSeats,
} from '~/utils/pixelOfficeLayout'

const props = defineProps<{
  events: SseEvent[]
  running: boolean
  handoffNodes?: HandoffNode[]
}>()

const iframeRef = ref<HTMLIFrameElement | null>(null)
const expanded = ref(false)
const ready = ref(false)
const adapterState = createPixelOfficeAdapterState()

const officeSrc = computed(() => '/pixel-office/index.html')

function postToOffice(msg: PixelOfficeHostMessage) {
  iframeRef.value?.contentWindow?.postMessage(msg, '*')
}

async function handleWebviewReady() {
  const soundEnabled = typeof localStorage !== 'undefined'
    ? localStorage.getItem('bossku-pixel-sound') !== 'false'
    : true
  postToOffice({ type: 'settingsLoaded', soundEnabled })

  const assetMessages = await loadAllPixelOfficeAssets()
  for (const msg of assetMessages) {
    postToOffice(msg as PixelOfficeHostMessage)
  }

  for (const msg of spawnCastMessages()) {
    postToOffice(msg)
  }

  const layout = await resolveOfficeLayout()
  postToOffice({ type: 'layoutLoaded', layout })
  ready.value = true
  syncEvents()
}

function handleOfficeMessage(event: MessageEvent) {
  if (event.source !== iframeRef.value?.contentWindow) return
  const msg = event.data as Record<string, unknown>
  if (!msg || typeof msg !== 'object') return

  if (msg.type === 'webviewReady') {
    void handleWebviewReady()
    return
  }

  if (msg.type === 'saveLayout' && msg.layout) {
    savePersistedLayout(msg.layout as Record<string, unknown>)
    return
  }

  if (msg.type === 'saveAgentSeats' && msg.seats) {
    savePersistedSeats(msg.seats as Record<number, { palette: number; hueShift: number; seatId: string | null }>)
    return
  }

  if (msg.type === 'setSoundEnabled') {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('bossku-pixel-sound', msg.enabled ? 'true' : 'false')
    }
    return
  }

  if (msg.type === 'requestExpand') {
    expanded.value = true
  }
}

function syncEvents() {
  if (!ready.value) return
  const messages = applyBosskuEventsToPixelOffice(props.events, adapterState)
  for (const msg of messages) {
    postToOffice(msg)
  }
}

watch(
  () => props.events,
  (evts, prev) => {
    if (prev && prev.length > 0 && evts.length === 0) {
      resetOffice()
    }
    syncEvents()
  },
  { deep: true },
)

watch(
  () => props.running,
  (running, wasRunning) => {
    if (wasRunning && !running) syncEvents()
  },
)

function resetOffice() {
  resetPixelOfficeAdapterState(adapterState)
  if (!ready.value) return
  // Iframe is still loaded — re-send spawn messages so agents reset visually.
  // Do NOT set ready = false; the iframe never re-fires webviewReady without a reload.
  for (const msg of spawnCastMessages()) {
    postToOffice(msg)
  }
}

defineExpose({ resetOffice })

onMounted(() => {
  window.addEventListener('message', handleOfficeMessage)
})

onBeforeUnmount(() => {
  window.removeEventListener('message', handleOfficeMessage)
})
</script>

<template>
  <section
    class="flex min-h-0 flex-col overflow-hidden bg-zinc-950"
    :class="expanded
      ? 'fixed inset-4 z-[100] rounded-lg border border-zinc-800 shadow-2xl'
      : 'h-full rounded-lg border border-zinc-800'"
  >
    <header class="flex shrink-0 items-center justify-between gap-2 border-b border-zinc-800 px-3 py-2">
      <div>
        <h3 class="text-sm font-medium text-zinc-100">Pixel office</h3>
        <p class="text-xs text-zinc-500">
          Live agent activity
          <span v-if="expanded" class="text-zinc-600"> · Layout: select item, R rotate, Delete remove</span>
        </p>
      </div>
      <div class="flex gap-2">
        <button
          v-if="!expanded"
          type="button"
          class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="expanded = true"
        >
          Expand
        </button>
        <button
          v-else
          type="button"
          class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="expanded = false"
        >
          Close
        </button>
      </div>
    </header>

    <div class="flex min-h-0 flex-1 flex-col">
    <ClientOnly>
      <iframe
        ref="iframeRef"
        :src="officeSrc"
        title="Bossku pixel office"
        class="w-full min-h-0 flex-1 border-0 bg-[#1a1a2e]"
        :class="expanded ? '' : 'min-h-[320px]'"
        :style="expanded ? { height: 'calc(100vh - 6rem)' } : undefined"
      />
      <template #fallback>
        <div
          class="w-full flex-1 bg-[#1a1a2e] min-h-[320px]"
          :style="expanded ? { height: 'calc(100vh - 6rem)' } : undefined"
          aria-hidden="true"
        />
      </template>
    </ClientOnly>
    </div>
  </section>
</template>
