import type { Skill } from '~/types/api'

export const PROJECT_UNDERSTANDING_COMMAND = 'project-understanding'

export type SlashCommandGroup = 'essential' | 'skills'

export type SlashCommandItem = {
  id: string
  label: string
  description: string
  group: SlashCommandGroup
  insert: string
}

export type SlashTrigger = {
  start: number
  end: number
  query: string
}

const SLASH_SPLIT_RE = /[\s\n\r\t]/u

/** Human-readable slash token for a skill (not the database UUID). */
export function skillSlashName(skill: Skill): string {
  const name = String(skill.name ?? '').trim()
  if (name) return name
  return String(skill.id ?? '').trim()
}

function skillSlashDescription(skill: Skill, slashName: string): string {
  const description = String(skill.description ?? '').trim()
  if (description && description.toLowerCase() !== slashName.toLowerCase()) {
    return description
  }
  const category = String(skill.category ?? '').trim()
  if (category && category.toLowerCase() !== slashName.toLowerCase()) {
    return category
  }
  return ''
}

export function buildProjectUnderstandingPrompt() {
  return [
    'You are BosskuAI project-understanding.',
    '',
    'Inspect the active repository first, then summarize:',
    '- project purpose',
    '- top-level structure and important folders/files',
    '- repo rules, conventions, and instructions',
    '- available BosskuAI skills, agents, and workflows you found',
    '- runtime stack, run/test/build commands, and setup notes',
    '- risks, hidden constraints, and what to verify before deeper work',
    '',
    'Keep the answer concise but complete.',
    'Do not edit files yet.',
    'End with the best next workflow for this repo.',
  ].join('\n')
}

export function buildSlashCommandItems(skills: Skill[]): SlashCommandItem[] {
  const items: SlashCommandItem[] = [
    {
      id: PROJECT_UNDERSTANDING_COMMAND,
      label: '/project-understanding',
      description: 'Map the repository before deeper work.',
      group: 'essential',
      insert: buildProjectUnderstandingPrompt(),
    },
  ]

  for (const skill of skills) {
    const id = String(skill.id ?? '').trim()
    if (!id || id === PROJECT_UNDERSTANDING_COMMAND) continue
    const slashName = skillSlashName(skill)
    if (!slashName) continue
    items.push({
      id,
      label: `/${slashName}`,
      description: skillSlashDescription(skill, slashName),
      group: 'skills',
      insert: `Use ${slashName} for this task:`,
    })
  }

  return items
}

export function findSlashTrigger(text: string, cursor = text.length): SlashTrigger | null {
  const safeCursor = Math.max(0, Math.min(cursor, text.length))
  let start = safeCursor

  while (start > 0) {
    const char = text[start - 1]
    if (SLASH_SPLIT_RE.test(char)) break
    start -= 1
  }

  const token = text.slice(start, safeCursor)
  if (!token.startsWith('/')) return null

  return {
    start,
    end: safeCursor,
    query: token.slice(1),
  }
}

export function replaceSlashTrigger(
  text: string,
  trigger: SlashTrigger,
  insert: string,
) {
  const before = text.slice(0, trigger.start)
  const after = text.slice(trigger.end)
  const value = `${before}${insert}${after}`
  const cursor = before.length + insert.length
  return { value, cursor }
}

function matchesSlashQuery(item: SlashCommandItem, query: string) {
  const q = query.trim().toLowerCase()
  if (!q) return true
  const fields = [item.label, item.description, item.insert]
  if (item.group === 'essential') {
    fields.push(item.id)
  }
  return fields.some(field => String(field).toLowerCase().includes(q))
}

export function filterSlashCommandItems(items: SlashCommandItem[], query: string) {
  const filtered = items.filter(item => matchesSlashQuery(item, query))
  return {
    essential: filtered.filter(item => item.group === 'essential'),
    skills: filtered.filter(item => item.group === 'skills'),
  }
}
