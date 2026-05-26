import { describe, expect, it } from 'vitest'
import {
  buildProjectUnderstandingPrompt,
  buildSlashCommandItems,
  filterSlashCommandItems,
  findSlashTrigger,
  replaceSlashTrigger,
} from '../utils/slashCommands'

describe('slashCommands', () => {
  it('builds the project-understanding prompt with the repo mapping steps', () => {
    const prompt = buildProjectUnderstandingPrompt()
    expect(prompt).toContain('project-understanding')
    expect(prompt).toContain('project purpose')
    expect(prompt).toContain('Do not edit files yet.')
  })

  it('pins project-understanding before active skills', () => {
    const items = buildSlashCommandItems([
      {
        id: 'laravel',
        name: 'Laravel playbook',
        description: 'Backend help',
        is_active: true,
      } as never,
      {
        id: 'build-fixer',
        name: 'Build fixer',
        description: 'Fix builds',
        is_active: true,
      } as never,
    ])

    expect(items[0].id).toBe('project-understanding')
    expect(items[0].group).toBe('essential')
    expect(items.filter(item => item.group === 'skills').map(item => item.id)).toEqual(['laravel', 'build-fixer'])
  })

  it('filters by id, label, and description', () => {
    const items = buildSlashCommandItems([
      {
        id: 'laravel',
        name: 'Laravel playbook',
        description: 'Backend help',
        is_active: true,
      } as never,
      {
        id: 'build-fixer',
        name: 'Build fixer',
        description: 'Fix builds',
        is_active: true,
      } as never,
    ])

    const filtered = filterSlashCommandItems(items, 'build')
    expect(filtered.essential.map(item => item.id)).toEqual(['project-understanding'])
    expect(filtered.skills.map(item => item.id)).toEqual(['build-fixer'])
  })

  it('detects the current slash token from the caret position', () => {
    expect(findSlashTrigger('/project-understanding')?.query).toBe('project-understanding')
    expect(findSlashTrigger('hello /laravel repo', 14)?.query).toBe('laravel')
    expect(findSlashTrigger('hello world')).toBeNull()
  })

  it('replaces only the slash token and returns the new caret position', () => {
    const trigger = findSlashTrigger('hello /laravel repo', 14)
    expect(trigger).not.toBeNull()
    const result = replaceSlashTrigger('hello /laravel repo', trigger!, 'Use laravel for this task:')
    expect(result.value).toBe('hello Use laravel for this task: repo')
    expect(result.cursor).toBe('hello Use laravel for this task:'.length)
  })
})
