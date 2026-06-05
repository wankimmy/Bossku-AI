import { describe, expect, it } from 'vitest'
import { SIDEBAR_LINKS } from '../utils/sidebarLinks'
import { APP_COMMANDS } from '../utils/appCommands'

describe('knowledge navigation', () => {
  it('exposes Knowledge in the left sidebar links', () => {
    expect(SIDEBAR_LINKS).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ to: '/knowledge', label: expect.stringContaining('Knowledge') }),
      ]),
    )
  })

  it('exposes Knowledge in the command bar commands', () => {
    expect(APP_COMMANDS).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ id: 'knowledge', label: expect.stringContaining('Knowledge') }),
      ]),
    )
  })
})
