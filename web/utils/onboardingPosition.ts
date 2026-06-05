import type { OnboardingPlacement } from '~/utils/onboardingSteps'

export const ONBOARDING_COMPACT_MAX_WIDTH = 1023

export function isCompactViewport(width = typeof window !== 'undefined' ? window.innerWidth : 1280): boolean {
  return width <= ONBOARDING_COMPACT_MAX_WIDTH
}

/** Prefer an element that is actually visible (mobile drawer vs hidden desktop nav). */
export function findVisibleTourTarget(selector: string): HTMLElement | null {
  if (typeof document === 'undefined') return null

  const nodes = document.querySelectorAll(selector)
  for (const node of nodes) {
    if (!(node instanceof HTMLElement)) continue
    const rect = node.getBoundingClientRect()
    if (rect.width > 0 && rect.height > 0) return node
  }

  return null
}

export type TooltipPositionStyle = Record<string, string>

export function computeTooltipStyle(options: {
  rect: DOMRect | null
  placement: OnboardingPlacement
  viewportWidth: number
  viewportHeight: number
  tooltipWidth: number
  tooltipHeight: number
  compact: boolean
}): TooltipPositionStyle {
  const {
    rect,
    placement,
    viewportWidth,
    viewportHeight,
    tooltipWidth,
    tooltipHeight,
    compact,
  } = options
  const margin = 12
  const edge = 16
  const safeBottom = 'max(1rem, env(safe-area-inset-bottom, 0px))'

  if (compact) {
    return {
      left: `${edge}px`,
      right: `${edge}px`,
      bottom: safeBottom,
      top: 'auto',
      transform: 'none',
      width: 'auto',
      maxWidth: 'none',
    }
  }

  if (!rect || placement === 'center') {
    return {
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      maxWidth: `min(400px, calc(100vw - ${edge * 2}px))`,
      width: `min(400px, calc(100vw - ${edge * 2}px))`,
    }
  }

  const resolved = resolvePlacementWithFlip(
    placement,
    rect,
    tooltipWidth,
    tooltipHeight,
    viewportWidth,
    viewportHeight,
    margin,
    edge,
  )

  const pos = anchorTooltip(resolved, rect, tooltipWidth, tooltipHeight, margin)
  const clamped = clampTooltipBox(
    pos,
    tooltipWidth,
    tooltipHeight,
    viewportWidth,
    viewportHeight,
    edge,
  )

  return {
    top: `${clamped.top}px`,
    left: `${clamped.left}px`,
    transform: clamped.transform,
    maxWidth: `min(400px, calc(100vw - ${edge * 2}px))`,
    width: `min(400px, calc(100vw - ${edge * 2}px))`,
  }
}

function resolvePlacementWithFlip(
  placement: OnboardingPlacement,
  rect: DOMRect,
  tooltipWidth: number,
  tooltipHeight: number,
  viewportWidth: number,
  viewportHeight: number,
  margin: number,
  edge: number,
): OnboardingPlacement {
  const spaceAbove = rect.top - edge - margin
  const spaceBelow = viewportHeight - rect.bottom - edge - margin
  const spaceLeft = rect.left - edge - margin
  const spaceRight = viewportWidth - rect.right - edge - margin

  if (placement === 'top' && spaceAbove < tooltipHeight) {
    return spaceBelow >= tooltipHeight ? 'bottom' : 'bottom'
  }
  if (placement === 'bottom' && spaceBelow < tooltipHeight) {
    return spaceAbove >= tooltipHeight ? 'top' : 'top'
  }
  if (placement === 'left' && spaceLeft < tooltipWidth) {
    return spaceRight >= tooltipWidth ? 'right' : 'right'
  }
  if (placement === 'right' && spaceRight < tooltipWidth) {
    return spaceLeft >= tooltipWidth ? 'left' : 'left'
  }

  return placement
}

function anchorTooltip(
  placement: OnboardingPlacement,
  rect: DOMRect,
  _tooltipWidth: number,
  _tooltipHeight: number,
  margin: number,
): { top: number; left: number; transform: string } {
  switch (placement) {
    case 'right':
      return {
        top: rect.top + rect.height / 2,
        left: rect.right + margin,
        transform: 'translateY(-50%)',
      }
    case 'left':
      return {
        top: rect.top + rect.height / 2,
        left: rect.left - margin,
        transform: 'translate(-100%, -50%)',
      }
    case 'top':
      return {
        top: rect.top - margin,
        left: rect.left + rect.width / 2,
        transform: 'translate(-50%, -100%)',
      }
    case 'bottom':
    default:
      return {
        top: rect.bottom + margin,
        left: rect.left + rect.width / 2,
        transform: 'translateX(-50%)',
      }
  }
}

/** Bounding box of tooltip after CSS transform is applied. */
function tooltipBox(
  top: number,
  left: number,
  width: number,
  height: number,
  transform: string,
): { top: number; left: number; right: number; bottom: number } {
  if (transform.includes('-100%') && transform.includes('-50%')) {
    return {
      top: top - height,
      left: left - width,
      right: left,
      bottom: top,
    }
  }
  if (transform.includes('-100%')) {
    return {
      top: top - height / 2,
      left: left - width,
      right: left,
      bottom: top + height / 2,
    }
  }
  if (transform.includes('-50%, -50%') || transform === 'translate(-50%, -50%)') {
    return {
      top: top - height / 2,
      left: left - width / 2,
      right: left + width / 2,
      bottom: top + height / 2,
    }
  }
  if (transform.includes('-50%, -100%')) {
    return {
      top: top - height,
      left: left - width / 2,
      right: left + width / 2,
      bottom: top,
    }
  }
  if (transform.includes('translateY(-50%)')) {
    return {
      top: top - height / 2,
      left,
      right: left + width,
      bottom: top + height / 2,
    }
  }
  if (transform.includes('translateX(-50%)')) {
    return {
      top,
      left: left - width / 2,
      right: left + width / 2,
      bottom: top + height,
    }
  }

  return { top, left, right: left + width, bottom: top + height }
}

function clampTooltipBox(
  pos: { top: number; left: number; transform: string },
  width: number,
  height: number,
  viewportWidth: number,
  viewportHeight: number,
  edge: number,
): { top: number; left: number; transform: string } {
  let { top, left, transform } = pos
  let box = tooltipBox(top, left, width, height, transform)

  if (box.left < edge) {
    left += edge - box.left
    box = tooltipBox(top, left, width, height, transform)
  }
  if (box.right > viewportWidth - edge) {
    left -= box.right - (viewportWidth - edge)
    box = tooltipBox(top, left, width, height, transform)
  }
  if (box.top < edge) {
    top += edge - box.top
    box = tooltipBox(top, left, width, height, transform)
  }
  if (box.bottom > viewportHeight - edge) {
    top -= box.bottom - (viewportHeight - edge)
  }

  return { top, left, transform }
}
