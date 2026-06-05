<script setup lang="ts">
import type { OnboardingPlacement, OnboardingStep } from '~/utils/onboardingSteps'
import {
  computeTooltipStyle,
  findVisibleTourTarget,
  isCompactViewport,
} from '~/utils/onboardingPosition'

const router = useRouter()
const route = useRoute()

const {
  active,
  stepIndex,
  steps,
  currentStep,
  hints,
  ensureSidebarVisible,
  next,
  prev,
  skip,
  complete,
} = useOnboarding()

const targetRect = ref<DOMRect | null>(null)
const tooltipStyle = ref<Record<string, string>>({})
const tooltipRef = ref<HTMLElement | null>(null)
const viewportWidth = ref(1280)
const viewportHeight = ref(800)
const isCompact = computed(() => isCompactViewport(viewportWidth.value))

const isCenterStep = computed(() => !currentStep.value?.selector || !targetRect.value)

const hintLine = computed(() => {
  const step = currentStep.value
  if (!step?.hintKey) return ''
  if (step.hintKey === 'ollama' && hints.value.hasOllama) {
    return '✓ Ollama URL is already configured.'
  }
  if (step.hintKey === 'project' && hints.value.hasProject) {
    return '✓ An active project is already set.'
  }
  return ''
})

const progressPercent = computed(() =>
  Math.round(((stepIndex.value + 1) / steps.length) * 100),
)

function isNavTarget(step: OnboardingStep): boolean {
  return Boolean(step.selector?.includes('data-tour="nav-'))
}

function updateViewport() {
  if (!import.meta.client) return
  viewportWidth.value = window.innerWidth
  viewportHeight.value = window.innerHeight
}

function padRect(rect: DOMRect, pad = 8): DOMRect {
  return new DOMRect(
    Math.max(0, rect.left - pad),
    Math.max(0, rect.top - pad),
    rect.width + pad * 2,
    rect.height + pad * 2,
  )
}

async function waitForTarget(selector: string, attempts = 20): Promise<HTMLElement | null> {
  for (let i = 0; i < attempts; i++) {
    await new Promise<void>(resolve => requestAnimationFrame(() => resolve()))
    const el = findVisibleTourTarget(selector)
    if (el) return el
    await new Promise(r => setTimeout(r, 60))
  }
  return null
}

function applyTooltipPosition(rect: DOMRect | null, placement: OnboardingPlacement) {
  const tipW = tooltipRef.value?.offsetWidth ?? (isCompact.value ? viewportWidth.value - 32 : 360)
  const tipH = tooltipRef.value?.offsetHeight ?? 200

  tooltipStyle.value = computeTooltipStyle({
    rect,
    placement,
    viewportWidth: viewportWidth.value,
    viewportHeight: viewportHeight.value,
    tooltipWidth: tipW,
    tooltipHeight: tipH,
    compact: isCompact.value,
  })
}

async function remeasureTooltip(rect: DOMRect | null, placement: OnboardingPlacement) {
  applyTooltipPosition(rect, placement)
  await nextTick()
  applyTooltipPosition(rect, placement)
  await new Promise<void>(r => requestAnimationFrame(() => r()))
  applyTooltipPosition(rect, placement)
}

async function syncStep() {
  const step = currentStep.value
  if (!step || !active.value) {
    targetRect.value = null
    return
  }

  updateViewport()

  if (step.route && route.path !== step.route) {
    await router.push(step.route)
  }

  if (isNavTarget(step)) {
    ensureSidebarVisible()
    await new Promise(r => setTimeout(r, isCompact.value ? 280 : 120))
  }

  await nextTick()

  if (!step.selector) {
    targetRect.value = null
    await remeasureTooltip(null, 'center')
    return
  }

  const el = await waitForTarget(step.selector)
  if (el) {
    targetRect.value = padRect(el.getBoundingClientRect())
    el.scrollIntoView({ block: 'nearest', behavior: 'smooth', inline: 'nearest' })
    await remeasureTooltip(targetRect.value, step.placement)
  }
  else {
    targetRect.value = null
    await remeasureTooltip(null, 'center')
  }
}

function onResize() {
  if (!active.value) return
  updateViewport()
  const step = currentStep.value
  if (!step?.selector) {
    void remeasureTooltip(null, 'center')
    return
  }
  const el = findVisibleTourTarget(step.selector)
  if (el) {
    targetRect.value = padRect(el.getBoundingClientRect())
    void remeasureTooltip(targetRect.value, step.placement)
  }
}

function onKeydown(e: KeyboardEvent) {
  if (!active.value) return
  if (e.key === 'Escape') {
    e.preventDefault()
    skip()
  }
}

function setBodyScrollLock(locked: boolean) {
  if (!import.meta.client) return
  document.documentElement.style.overflow = locked ? 'hidden' : ''
  document.body.style.overflow = locked ? 'hidden' : ''
}

watch(active, (on) => {
  setBodyScrollLock(on)
  if (on) void syncStep()
}, { immediate: true })

watch(stepIndex, () => {
  if (active.value) void syncStep()
})

watch(() => route.path, () => {
  if (active.value) void syncStep()
})

onMounted(() => {
  if (import.meta.client) {
    updateViewport()
    window.addEventListener('resize', onResize)
    window.addEventListener('scroll', onResize, true)
    document.addEventListener('keydown', onKeydown)
  }
})

onUnmounted(() => {
  setBodyScrollLock(false)
  if (import.meta.client) {
    window.removeEventListener('resize', onResize)
    window.removeEventListener('scroll', onResize, true)
    document.removeEventListener('keydown', onKeydown)
  }
})

const dimPanels = computed(() => {
  const r = targetRect.value
  if (!r || isCenterStep.value) return null
  const vw = viewportWidth.value
  const vh = viewportHeight.value
  return {
    top: { top: 0, left: 0, width: `${vw}px`, height: `${Math.max(0, r.top)}px` },
    left: { top: `${r.top}px`, left: 0, width: `${Math.max(0, r.left)}px`, height: `${r.height}px` },
    right: { top: `${r.top}px`, left: `${r.left + r.width}px`, width: `${Math.max(0, vw - r.left - r.width)}px`, height: `${r.height}px` },
    bottom: { top: `${r.top + r.height}px`, left: 0, width: `${vw}px`, height: `${Math.max(0, vh - r.top - r.height)}px` },
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="active"
      class="fixed inset-0 z-[100] touch-manipulation"
      role="dialog"
      aria-modal="true"
      :aria-label="currentStep?.title ?? 'Onboarding tour'"
    >
      <!-- Dim overlay -->
      <template v-if="dimPanels">
        <div
          v-for="(panel, key) in dimPanels"
          :key="key"
          class="fixed bg-black/75 backdrop-blur-[2px] pointer-events-auto"
          :style="panel"
          @click="skip"
        />
        <div
          class="fixed rounded-lg ring-2 ring-emerald-400 shadow-[0_0_0_4px_rgba(16,185,129,0.25)] pointer-events-none"
          :style="{
            top: `${targetRect!.top}px`,
            left: `${targetRect!.left}px`,
            width: `${targetRect!.width}px`,
            height: `${targetRect!.height}px`,
          }"
        />
      </template>
      <div
        v-else
        class="fixed inset-0 bg-black/75 backdrop-blur-[2px] pointer-events-auto"
        @click="skip"
      />

      <!-- Tooltip / bottom sheet -->
      <div
        ref="tooltipRef"
        class="onboarding-tooltip relative fixed z-[101] pointer-events-auto flex flex-col overflow-hidden border border-zinc-700 bg-zinc-900 shadow-2xl"
        :class="isCompact
          ? 'inset-x-4 bottom-0 max-h-[min(70vh,520px)] rounded-t-2xl rounded-b-none pb-[env(safe-area-inset-bottom,0px)]'
          : 'rounded-xl p-4'"
        :style="isCompact ? {} : tooltipStyle"
      >
        <div
          v-if="isCompact"
          class="flex shrink-0 justify-center pt-2 pb-1"
          aria-hidden="true"
        >
          <span class="h-1 w-10 rounded-full bg-zinc-600" />
        </div>

        <div
          class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
          :class="isCompact ? 'px-4 pt-1 pb-2' : ''"
        >
          <div class="mb-2 flex items-center gap-2">
            <div
              class="h-1 flex-1 overflow-hidden rounded-full bg-zinc-800"
              role="progressbar"
              :aria-valuenow="progressPercent"
              aria-valuemin="0"
              aria-valuemax="100"
            >
              <div
                class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                :style="{ width: `${progressPercent}%` }"
              />
            </div>
            <span class="shrink-0 text-[10px] tabular-nums text-zinc-500">
              {{ stepIndex + 1 }}/{{ steps.length }}
            </span>
          </div>

          <button
            type="button"
            class="absolute right-3 top-3 rounded-md p-2 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200 lg:top-4 lg:right-4"
            :class="isCompact ? 'top-2 right-2' : ''"
            aria-label="Close tour"
            @click="skip"
          >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <h2 class="pr-8 text-base font-semibold leading-snug text-zinc-100 sm:text-lg">
            {{ currentStep?.title }}
          </h2>
          <p class="mt-2 text-sm leading-relaxed text-zinc-400">
            {{ currentStep?.body }}
          </p>
          <p v-if="hintLine" class="mt-2 text-xs text-emerald-400">
            {{ hintLine }}
          </p>
          <p
            v-if="isCompact && currentStep && isNavTarget(currentStep)"
            class="mt-2 text-xs text-zinc-500"
          >
            Tip: use the menu (☰) if you need to open the sidebar again.
          </p>
        </div>

        <div
          class="shrink-0 border-t border-zinc-800 bg-zinc-900/95"
          :class="isCompact ? 'px-4 py-3 pb-4' : 'mt-4 border-t-0 pt-0'"
        >
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <button
              type="button"
              class="min-h-11 text-sm text-zinc-500 underline hover:text-zinc-300 sm:min-h-0 sm:text-xs"
              @click="skip"
            >
              Skip tour
            </button>
            <div class="flex gap-2">
              <button
                v-if="stepIndex > 0"
                type="button"
                class="min-h-11 flex-1 rounded-lg border border-zinc-600 px-4 py-2.5 text-sm text-zinc-300 hover:bg-zinc-800 sm:min-h-0 sm:flex-none sm:rounded-md sm:px-3 sm:py-1.5 sm:text-xs"
                @click="prev"
              >
                Back
              </button>
              <button
                type="button"
                class="min-h-11 flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 sm:min-h-0 sm:flex-none sm:rounded-md sm:px-3 sm:py-1.5 sm:text-xs"
                @click="stepIndex >= steps.length - 1 ? complete() : next()"
              >
                {{ stepIndex >= steps.length - 1 ? 'Finish' : 'Next' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.onboarding-tooltip {
  box-shadow:
    0 -8px 32px rgba(0, 0, 0, 0.45),
    0 0 0 1px rgba(255, 255, 255, 0.04);
}

@media (min-width: 1024px) {
  .onboarding-tooltip:not(.inset-x-4) {
    /* desktop uses inline :style positioning */
  }
}
</style>
