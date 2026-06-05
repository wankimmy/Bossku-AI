import { describe, expect, it } from 'vitest'

interface Command {
  id: string
  label: string
  action: () => void
}

function filterCommands(commands: Command[], query: string): Command[] {
  if (!query.trim()) return commands
  const q = query.toLowerCase()
  return commands.filter(c => c.label.toLowerCase().includes(q))
}

describe('CommandBar filtering logic', () => {
  const commands: Command[] = [
    { id: 'dashboard', label: 'Go to Dashboard', action: () => {} },
    { id: 'runs', label: 'Go to Runs', action: () => {} },
    { id: 'brain', label: 'Open Brain', action: () => {} },
    { id: 'providers', label: 'Manage Providers', action: () => {} },
    { id: 'soul', label: 'Edit Soul', action: () => {} },
  ]

  it('returns all commands for empty query', () => {
    expect(filterCommands(commands, '')).toHaveLength(commands.length)
  })

  it('filters by label substring (case insensitive)', () => {
    const results = filterCommands(commands, 'go to')
    expect(results).toHaveLength(2)
    expect(results.map(c => c.id)).toContain('dashboard')
    expect(results.map(c => c.id)).toContain('runs')
  })

  it('returns empty for non-matching query', () => {
    expect(filterCommands(commands, 'zzznomatch')).toHaveLength(0)
  })

  it('single match for unique label fragment', () => {
    const results = filterCommands(commands, 'soul')
    expect(results).toHaveLength(1)
    expect(results[0].id).toBe('soul')
  })

  it('is case insensitive', () => {
    expect(filterCommands(commands, 'BRAIN')).toHaveLength(1)
    expect(filterCommands(commands, 'brain')).toHaveLength(1)
  })
})
