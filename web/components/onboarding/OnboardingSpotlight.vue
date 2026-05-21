<script setup lang="ts">
import type { OnboardingPlacement, OnboardingStep } from '~/utils/onboardingSteps'

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

const isCenterStep = computed(() => !currentStep.value?.selector)

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

function isNavTarget(step: OnboardingStep): boolean {
  return Boolean(step.selector?.includes('data-tour="nav-'))
}

async function waitForTarget(selector: string, attempts = 12): Promise<HTMLElement | null> {
  for (let i = 0; i < attempts; i++) {
    await new Promise<void>(resolve => requestAnimationFrame(() => resolve()))
    const el = document.querySelector(selector)
    if (el instanceof HTMLElement) {
      const r = el.getBoundingClientRect()
      if (r.width > 0 && r.height > 0) return el
    }
    await new Promise(r => setTimeout(r, 50))
  }
  return null
}

function padRect(rect: DOMRect, pad = 8): DOMRect {
  return new DOMRect(
    Math.max(0, rect.left - pad),
    Math.max(0, rect.top - pad),
    rect.width + pad * 2,
    rect.height + pad * 2,
  )
}

function positionTooltip(rect: DOMRect | null, placement: OnboardingPlacement) {
  const margin = 12
  const vw = window.innerWidth
  const vh = window.innerHeight
  const tipW = 320
  const tipH = tooltipRef.value?.offsetHeight ?? 180

  if (!rect || placement === 'center') {
    tooltipStyle.value = {
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      maxWidth: 'min(360px, calc(100vw - 32px))',
    }
    return
  }

  let top = rect.bottom + margin
  let left = rect.left
  let transform = ''

  if (placement === 'right') {
    top = rect.top + rect.height / 2
    left = rect.right + margin
    transform = 'translateY(-50%)'
  }
  else if (placement === 'left') {
    top = rect.top + rect.height / 2
    left = rect.left - margin - tipW
    transform = 'translate(-100%, -50%)'
  }
  else if (placement === 'top') {
    top = rect.top - margin - tipH
    left = rect.left + rect.width / 2
    transform = 'translate(-50%, -100%)'
  }
  else {
    top = rect.bottom + margin
    left = rect.left + rect.width / 2
    transform = 'translateX(-50%)'
  }

  if (left + tipW > vw - 16) left = vw - tipW - 16
  if (left < 16) left = 16
  if (top + tipH > vh - 16) top = Math.max(16, rect.top - margin - tipH)
  if (top < 16) top = 16

  tooltipStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
    transform,
    maxWidth: 'min(360px, calc(100vw - 32px))',
  }
}

async function syncStep() {
  const step = currentStep.value
  if (!step || !active.value) {
    targetRect.value = null
    return
  }

  if (step.route && route.path !== step.route) {
    await router.push(step.route)
  }

  if (isNavTarget(step)) {
    ensureSidebarVisible()
  }

  await nextTick()

  if (!step.selector) {
    targetRect.value = null
    positionTooltip(null, 'center')
    return
  }

  const el = await waitForTarget(step.selector)
  if (el) {
    targetRect.value = padRect(el.getBoundingClientRect())
    positionTooltip(targetRect.value, step.placement)
    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  }
  else {
    targetRect.value = null
    positionTooltip(null, 'center')
  }
}

function onResize() {
  if (!active.value || !currentStep.value?.selector) return
  const el = document.querySelector(currentStep.value.selector)
  if (el instanceof HTMLElement) {
    targetRect.value = padRect(el.getBoundingClientRect())
    positionTooltip(targetRect.value, currentStep.value.placement)
  }
}

function onKeydown(e: KeyboardEvent) {
  if (!active.value) return
  if (e.key === 'Escape') {
    e.preventDefault()
    skip()
  }
}

watch([active, stepIndex], () => {
  if (active.value) void syncStep()
}, { immediate: true })

watch(() => route.path, () => {
  if (active.value) void syncStep()
})

onMounted(() => {
  if (import.meta.client) {
    window.addEventListener('resize', onResize)
    window.addEventListener('scroll', onResize, true)
    document.addEventListener('keydown', onKeydown)
  }
})

onUnmounted(() => {
  if (import.meta.client) {
    window.removeEventListener('resize', onResize)
    window.removeEventListener('scroll', onResize, true)
    document.removeEventListener('keydown', onKeydown)
  }
})

const dimPanels = computed(() => {
  const r = targetRect.value
  if (!r) return null
  const vw = window.innerWidth
  const vh = window.innerHeight
  return {
    top: { top: 0, left: 0, width: `${vw}px`, height: `${r.top}px` },
    left: { top: `${r.top}px`, left: 0, width: `${r.left}px`, height: `${r.height}px` },
    right: { top: `${r.top}px`, left: `${r.left + r.width}px`, width: `${vw - r.left - r.width}px`, height: `${r.height}px` },
    bottom: { top: `${r.top + r.height}px`, left: 0, width: `${vw}px`, height: `${vh - r.top - r.height}px` },
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="active"
      class="fixed inset-0 z-[100]"
      role="dialog"
      aria-modal="true"
      :aria-label="currentStep?.title ?? 'Onboarding tour'"
    >
      <!-- Dim overlay -->
      <template v-if="dimPanels">
        <div
          v-for="(panel, key) in dimPanels"
          :key="key"
          class="fixed bg-black/70 pointer-events-auto"
          :style="panel"
          @click="skip"
        />
        <div
          class="fixed rounded-md ring-2 ring-emerald-400/80 pointer-events-none"
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
        class="fixed inset-0 bg-black/70 pointer-events-auto"
        @click="skip"
      />

      <!-- Tooltip -->
      <div
        ref="tooltipRef"
        class="fixed z-[101] rounded-lg border border-zinc-700 bg-zinc-900 p-4 shadow-xl pointer-events-auto"
        :style="tooltipStyle"
        @click.stop
      >
        <p class="text-[10px] uppercase tracking-wider text-zinc-500 mb-1">
          Step {{ stepIndex + 1 }} of {{ steps.length }}
        </p>
        <h2 class="text-base font-semibold text-zinc-100 mb-2">
          {{ currentStep?.title }}
        </h2>
        <p class="text-sm text-zinc-400 leading-relaxed mb-2">
          {{ currentStep?.body }}
        </p>
        <p v-if="hintLine" class="text-xs text-emerald-400 mb-3">
          {{ hintLine }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2 mt-4">
          <button
            type="button"
            class="text-xs text-zinc-500 hover:text-zinc-300 underline"
            @click="skip"
          >
            Skip tour
          </button>
          <div class="flex gap-2">
            <button
              v-if="stepIndex > 0"
              type="button"
              class="rounded-md border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800"
              @click="prev"
            >
              Back
            </button>
            <button
              type="button"
              class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500"
              @click="stepIndex >= steps.length - 1 ? complete() : next()"
            >
              {{ stepIndex >= steps.length - 1 ? 'Finish' : 'Next' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
