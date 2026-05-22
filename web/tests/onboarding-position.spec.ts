import { describe, expect, it } from 'vitest'
import {
  computeTooltipStyle,
  isCompactViewport,
  type TooltipPositionStyle,
} from '../utils/onboardingPosition'

function boxFromStyle(
  style: TooltipPositionStyle,
  left: number,
  top: number,
  width: number,
  height: number,
) {
  const transform = style.transform ?? ''
  if (transform.includes('-100%') && transform.includes('-50%')) {
    return { top: top - height, left: left - width, right: left, bottom: top }
  }
  if (transform.includes('-100%')) {
    return {
      top: top - height / 2,
      left: left - width,
      right: left,
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

describe('onboardingPosition', () => {
  it('treats viewports at 1023px and below as compact', () => {
    expect(isCompactViewport(1023)).toBe(true)
    expect(isCompactViewport(390)).toBe(true)
    expect(isCompactViewport(1024)).toBe(false)
  })

  it('uses bottom sheet layout on compact viewports', () => {
    const style = computeTooltipStyle({
      rect: new DOMRect(10, 10, 100, 40),
      placement: 'right',
      viewportWidth: 390,
      viewportHeight: 844,
      tooltipWidth: 358,
      tooltipHeight: 200,
      compact: true,
    })

    expect(style.left).toBe('16px')
    expect(style.right).toBe('16px')
    expect(style.bottom).toContain('safe-area')
    expect(style.top).toBe('auto')
  })

  it('clamps desktop tooltip within viewport', () => {
    const w = 360
    const h = 180
    const style = computeTooltipStyle({
      rect: new DOMRect(300, 100, 80, 32),
      placement: 'right',
      viewportWidth: 400,
      viewportHeight: 700,
      tooltipWidth: w,
      tooltipHeight: h,
      compact: false,
    })

    const top = parseInt(style.top ?? '0', 10)
    const left = parseInt(style.left ?? '0', 10)
    const box = boxFromStyle(style, left, top, w, h)
    expect(box.left).toBeGreaterThanOrEqual(16)
    expect(box.right).toBeLessThanOrEqual(400 - 16)
    expect(box.top).toBeGreaterThanOrEqual(16)
    expect(box.bottom).toBeLessThanOrEqual(700 - 16)
  })

  it('keeps top placement visible (no double height offset)', () => {
    const w = 360
    const h = 220
    const targetTop = 400
    const style = computeTooltipStyle({
      rect: new DOMRect(200, targetTop, 400, 80),
      placement: 'top',
      viewportWidth: 1280,
      viewportHeight: 800,
      tooltipWidth: w,
      tooltipHeight: h,
      compact: false,
    })

    const top = parseInt(style.top ?? '0', 10)
    const left = parseInt(style.left ?? '0', 10)
    const box = boxFromStyle(style, left, top, w, h)
    expect(box.top).toBeGreaterThanOrEqual(16)
    expect(box.bottom).toBeLessThanOrEqual(targetTop)
    expect(style.transform).toContain('-100%')
  })

  it('flips top to bottom when target is near viewport top', () => {
    const style = computeTooltipStyle({
      rect: new DOMRect(100, 24, 500, 56),
      placement: 'top',
      viewportWidth: 1280,
      viewportHeight: 800,
      tooltipWidth: 360,
      tooltipHeight: 240,
      compact: false,
    })

    const top = parseInt(style.top ?? '0', 10)
    expect(top).toBeGreaterThan(24)
    expect(style.transform).toBe('translateX(-50%)')
  })
})
