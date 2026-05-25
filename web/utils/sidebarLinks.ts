export type SidebarLink = {
  to: string
  label: string
  tourId?: string
}

export const SIDEBAR_LINKS: SidebarLink[] = [
  { to: '/', label: '🏠 Chat', tourId: 'nav-chat' },
  { to: '/conversations', label: '💬 Conversations' },
  { to: '/dashboard', label: '📊 Dashboard' },
  { to: '/runs', label: '▶ Runs', tourId: 'nav-runs' },
  { to: '/project', label: '📁 Project', tourId: 'nav-project' },
  { to: '/agents', label: '🤖 Agents' },
  { to: '/personas', label: '🎭 Personas', tourId: 'nav-personas' },
  { to: '/data', label: '🗄 Data' },
  { to: '/skills', label: '⚡ Skills' },
  { to: '/memory', label: '🧠 Memory' },
  { to: '/knowledge', label: '📚 Knowledge' },
  { to: '/brain', label: '🔬 Brain' },
  { to: '/knowledge-graph', label: '🕸 Knowledge Graph' },
  { to: '/skills-graph', label: '📈 Skills Graph' },
  { to: '/plugins', label: '🔌 Plugins' },
  { to: '/logs', label: '📋 Logs' },
  { to: '/usage', label: '💰 Usage' },
  { to: '/feedback', label: '💬 Feedback' },
  { to: '/soul', label: '✨ Soul' },
  { to: '/settings/models', label: '⚙ Settings', tourId: 'nav-settings' },
]
