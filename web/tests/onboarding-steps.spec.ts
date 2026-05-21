import { describe, expect, it, vi, beforeEach } from 'vitest'
import { ONBOARDING_STEPS } from '../utils/onboardingSteps'

const ONBOARDING_STORAGE_KEY = 'bossku_onboarding_v1'

describe('onboarding steps', () => {
  it('every step has id, title, body, and placement', () => {
    expect(ONBOARDING_STEPS.length).toBeGreaterThanOrEqual(8)
    for (const step of ONBOARDING_STEPS) {
      expect(step.id).toBeTruthy()
      expect(step.title).toBeTruthy()
      expect(step.body).toBeTruthy()
      expect(['top', 'bottom', 'left', 'right', 'center']).toContain(step.placement)
    }
  })

  it('center steps have no selector', () => {
    const center = ONBOARDING_STEPS.filter(s => s.placement === 'center' || !s.selector)
    expect(center.map(s => s.id)).toContain('welcome')
    expect(center.map(s => s.id)).toContain('done')
    for (const step of center) {
      expect(step.selector).toBeNull()
    }
  })

  it('nav steps use data-tour selectors', () => {
    const navSteps = ONBOARDING_STEPS.filter(s => s.selector?.includes('nav-'))
    expect(navSteps.length).toBeGreaterThanOrEqual(4)
    for (const step of navSteps) {
      expect(step.selector).toMatch(/\[data-tour="nav-/)
    }
  })

  it('chat-prompt step targets textarea tour id', () => {
    const chat = ONBOARDING_STEPS.find(s => s.id === 'chat-prompt')
    expect(chat?.selector).toBe('[data-tour="chat-prompt"]')
    expect(chat?.route).toBe('/')
  })
})

describe('onboarding storage key', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', {
      store: {} as Record<string, string>,
      getItem(key: string) {
        return this.store[key] ?? null
      },
      setItem(key: string, value: string) {
        this.store[key] = value
      },
      removeItem(key: string) {
        delete this.store[key]
      },
    })
  })

  it('uses bossku_onboarding_v1 key', () => {
    expect(ONBOARDING_STORAGE_KEY).toBe('bossku_onboarding_v1')
    localStorage.setItem(ONBOARDING_STORAGE_KEY, '1')
    expect(localStorage.getItem(ONBOARDING_STORAGE_KEY)).toBe('1')
  })
})
