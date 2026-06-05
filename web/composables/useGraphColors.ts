export type GraphColorMode = 'category' | 'depth'

const categoryColors: Record<string, string> = {
  engineering: '#6da3ff',
  infra: '#b58cff',
  runtime: '#f48fb1',
  data: '#5dd29c',
  security: '#f06a6a',
  growth: '#f5c062',
  sales: '#ff9966',
  design: '#62d2dc',
  operating: '#ffffff',
  research: '#c0a3ff',
  quality: '#f0a3a3',
  meta: '#888888',
  other: '#555555',
  skill: '#10b981',
  run: '#3b82f6',
  memory: '#8b5cf6',
  agent: '#06b6d4',
  rule: '#f59e0b',
  playbook: '#14b8a6',
}

const depthColors: Record<string, string> = {
  DEEP: '#5dd29c',
  OK: '#f5c062',
  THIN: '#f06a6a',
}

export function categoryColor(cat: string): string {
  return categoryColors[cat] ?? '#888888'
}

export function depthColor(depth: string): string {
  return depthColors[depth] ?? depthColors.THIN
}

export function nodeFill(colorMode: GraphColorMode, node: { category: string; depth: string }): string {
  return colorMode === 'depth' ? depthColor(node.depth) : categoryColor(node.category)
}

export const categoryLegendItems = [
  ['engineering', '#6da3ff'],
  ['infra', '#b58cff'],
  ['runtime', '#f48fb1'],
  ['data', '#5dd29c'],
  ['security', '#f06a6a'],
  ['growth', '#f5c062'],
  ['sales', '#ff9966'],
  ['design', '#62d2dc'],
  ['operating', '#ffffff'],
  ['research', '#c0a3ff'],
  ['quality', '#f0a3a3'],
  ['meta', '#888888'],
] as const

export const depthLegendItems = [
  ['DEEP (≥250 lines)', '#5dd29c'],
  ['OK (100–250)', '#f5c062'],
  ['THIN (<100)', '#f06a6a'],
] as const
