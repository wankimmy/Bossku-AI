import { agentTheme } from './agentTheme'

export type DashboardCardVariant =
  | 'status'
  | 'backend'
  | 'memory'
  | 'reasoning'
  | 'coding'
  | 'review'
  | 'fast'

export interface DashboardCardStyle {
  container: string
  label: string
  value: string
  icon?: string
  dot?: string
}

const statusActive: DashboardCardStyle = {
  container: 'border-emerald-800/60 bg-emerald-950/50 dark:border-emerald-700/60 dark:bg-emerald-950/40',
  label: 'text-emerald-600 dark:text-emerald-400/90',
  value: 'text-emerald-950 dark:text-emerald-100',
  dot: 'bg-emerald-500',
}

const statusIdle: DashboardCardStyle = {
  container: 'border-zinc-300 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/80',
  label: 'text-zinc-500',
  value: 'text-zinc-900 dark:text-zinc-100',
  dot: 'bg-zinc-500',
}

const backend: DashboardCardStyle = {
  container: 'border-indigo-800/50 bg-indigo-950/40 dark:border-indigo-700/50 dark:bg-indigo-950/30',
  label: 'text-indigo-600 dark:text-indigo-400/90',
  value: 'text-indigo-950 dark:text-indigo-100',
  icon: '☁',
}

const memoryActive: DashboardCardStyle = {
  container: 'border-violet-800/50 bg-violet-950/40 dark:border-violet-700/50 dark:bg-violet-950/30',
  label: 'text-violet-600 dark:text-violet-400/90',
  value: 'text-violet-950 dark:text-violet-100',
  icon: agentTheme.memory.icon,
}

const memoryIdle: DashboardCardStyle = {
  container: 'border-zinc-300 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/80',
  label: 'text-zinc-500',
  value: 'text-zinc-900 dark:text-zinc-100',
  icon: agentTheme.memory.icon,
}

const agentLabelClasses: Partial<Record<keyof typeof agentTheme, string>> = {
  orchestrator: 'text-blue-600 dark:text-blue-400/80',
  executor: 'text-emerald-600 dark:text-emerald-400/80',
  auditor: 'text-amber-600 dark:text-amber-400/80',
  router: 'text-cyan-600 dark:text-cyan-400/80',
}

function fromAgent(role: keyof typeof agentTheme): DashboardCardStyle {
  const t = agentTheme[role]
  return {
    container: `${t.border} ${t.bg}`,
    label: agentLabelClasses[role] ?? 'text-zinc-500',
    value: 'text-zinc-950 dark:text-zinc-50',
    icon: t.icon,
    dot: t.dot,
  }
}

export function getDashboardCardStyle(
  variant: DashboardCardVariant,
  options?: { active?: boolean },
): DashboardCardStyle {
  switch (variant) {
    case 'status':
      return options?.active ? statusActive : statusIdle
    case 'backend':
      return backend
    case 'memory':
      return options?.active ? memoryActive : memoryIdle
    case 'reasoning':
      return fromAgent('orchestrator')
    case 'coding':
      return fromAgent('executor')
    case 'review':
      return fromAgent('auditor')
    case 'fast':
      return fromAgent('router')
    default:
      return statusIdle
  }
}
