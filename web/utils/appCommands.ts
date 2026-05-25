export type AppCommandDefinition = {
  id: string
  label: string
  to?: string
  action?: 'toggle-logs'
}

export const APP_COMMANDS: AppCommandDefinition[] = [
  { id: 'dashboard', label: '📊 Go to Dashboard', to: '/dashboard' },
  { id: 'runs', label: '▶ Go to Runs', to: '/runs' },
  { id: 'project', label: '📁 Go to Project', to: '/project' },
  { id: 'agents', label: '🤖 Go to Agents', to: '/agents' },
  { id: 'skills', label: '⚡ Go to Skills', to: '/skills' },
  { id: 'memory', label: '🧠 Go to Memory', to: '/memory' },
  { id: 'knowledge', label: '📚 Go to Knowledge', to: '/knowledge' },
  { id: 'brain', label: '🔬 Go to Brain', to: '/brain' },
  { id: 'plugins', label: '🔌 Go to Plugins', to: '/plugins' },
  { id: 'logs', label: '📋 Go to Logs', to: '/logs' },
  { id: 'usage', label: '💰 Go to Usage', to: '/usage' },
  { id: 'feedback', label: '💬 Go to Feedback', to: '/feedback' },
  { id: 'soul', label: '✨ Go to Soul', to: '/soul' },
  { id: 'settings', label: '⚙ Go to Settings', to: '/settings/providers' },
  { id: 'toggle-logs', label: '📋 Toggle Log Drawer', action: 'toggle-logs' },
]
