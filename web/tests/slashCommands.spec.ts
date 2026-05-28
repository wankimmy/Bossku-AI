import { describe, expect, it } from 'vitest'
import {
  buildProjectUnderstandingPrompt,
  buildSlashCommandItems,
  filterSlashCommandItems,
  findSlashTrigger,
  replaceSlashTrigger,
  skillSlashName,
} from '../utils/slashCommands'

const SKILL_UUID = 'a1e38124-918a-493d-804e-1a5932992833'

describe('slashCommands', () => {
  it('builds the project-understanding prompt with the repo mapping steps', () => {
    const prompt = buildProjectUnderstandingPrompt()
    expect(prompt).toContain('project-understanding')
    expect(prompt).toContain('project purpose')
    expect(prompt).toContain('Do not edit files yet.')
  })

  it('uses skill name for label and insert, not database UUID', () => {
    const skill = {
      id: SKILL_UUID,
      name: 'bosskuai-laravel',
      description: 'Laravel playbook',
      is_active: true,
    } as never

    expect(skillSlashName(skill)).toBe('bosskuai-laravel')

    const items = buildSlashCommandItems([skill])
    const skillItem = items.find(item => item.group === 'skills')
    expect(skillItem?.id).toBe(SKILL_UUID)
    expect(skillItem?.label).toBe('/bosskuai-laravel')
    expect(skillItem?.label).not.toContain(SKILL_UUID)
    expect(skillItem?.insert).toBe('Use bosskuai-laravel for this task:')
    expect(skillItem?.description).toBe('Laravel playbook')
  })

  it('omits description when it duplicates the skill name', () => {
    const items = buildSlashCommandItems([
      {
        id: SKILL_UUID,
        name: 'bosskuai-ai-model-selection',
        description: 'bosskuai-ai-model-selection',
        is_active: true,
      } as never,
    ])

    const skillItem = items.find(item => item.group === 'skills')
    expect(skillItem?.label).toBe('/bosskuai-ai-model-selection')
    expect(skillItem?.description).toBe('')
  })

  it('pins project-understanding before active skills', () => {
    const items = buildSlashCommandItems([
      {
        id: SKILL_UUID,
        name: 'bosskuai-laravel',
        description: 'Backend help',
        is_active: true,
      } as never,
      {
        id: 'b2e38124-918a-493d-804e-1a5932992844',
        name: 'bosskuai-build-fixer',
        description: 'Fix builds',
        is_active: true,
      } as never,
    ])

    expect(items[0].id).toBe('project-understanding')
    expect(items[0].group).toBe('essential')
    expect(items.filter(item => item.group === 'skills').map(item => item.id)).toEqual([
      SKILL_UUID,
      'b2e38124-918a-493d-804e-1a5932992844',
    ])
  })

  it('filters by skill name in label, not UUID', () => {
    const items = buildSlashCommandItems([
      {
        id: SKILL_UUID,
        name: 'bosskuai-laravel',
        description: 'Backend help',
        is_active: true,
      } as never,
      {
        id: 'b2e38124-918a-493d-804e-1a5932992844',
        name: 'bosskuai-build-fixer',
        description: 'Fix builds',
        is_active: true,
      } as never,
    ])

    const filtered = filterSlashCommandItems(items, 'build')
    expect(filtered.essential.map(item => item.id)).toEqual(['project-understanding'])
    expect(filtered.skills.map(item => item.id)).toEqual(['b2e38124-918a-493d-804e-1a5932992844'])

    const byUuid = filterSlashCommandItems(items, 'a1e38124')
    expect(byUuid.skills).toHaveLength(0)
  })

  it('detects the current slash token from the caret position', () => {
    expect(findSlashTrigger('/project-understanding')?.query).toBe('project-understanding')
    expect(findSlashTrigger('/bosskuai-laravel')?.query).toBe('bosskuai-laravel')
    const inline = 'hello /bosskuai-laravel repo'
    const cursor = inline.indexOf('/bosskuai-laravel') + '/bosskuai-laravel'.length
    expect(findSlashTrigger(inline, cursor)?.query).toBe('bosskuai-laravel')
    expect(findSlashTrigger('hello world')).toBeNull()
  })

  it('replaces only the slash token and returns the new caret position', () => {
    const inline = 'hello /bosskuai-laravel repo'
    const cursor = inline.indexOf('/bosskuai-laravel') + '/bosskuai-laravel'.length
    const trigger = findSlashTrigger(inline, cursor)
    expect(trigger).not.toBeNull()
    const result = replaceSlashTrigger(
      inline,
      trigger!,
      'Use bosskuai-laravel for this task:',
    )
    expect(result.value).toBe('hello Use bosskuai-laravel for this task: repo')
    expect(result.cursor).toBe('hello Use bosskuai-laravel for this task:'.length)
  })
})
