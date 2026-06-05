import { describe, expect, it } from 'vitest'
import { createEmptyLandingChatStore } from '../composables/useLandingChat'

describe('landing chat SSR hydration', () => {
  it('createEmptyLandingChatStore returns a stable empty v2 store', () => {
    const store = createEmptyLandingChatStore()
    expect(store).toEqual({
      version: 2,
      activeId: null,
      conversations: [],
    })
  })

  it('each call returns a fresh object (no shared reference)', () => {
    const a = createEmptyLandingChatStore()
    const b = createEmptyLandingChatStore()
    expect(a).not.toBe(b)
    a.conversations.push({
      id: 'conv_test',
      title: 'Test',
      updatedAt: 0,
      createdAt: 0,
      turns: [],
    })
    expect(b.conversations).toHaveLength(0)
  })
})
