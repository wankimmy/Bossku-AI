export type SidebarLink = {
  to: string
  label: string
  /** Emoji glyph rendered in the nav "slot" tile. */
  icon: string
  tourId?: string
}

export type SidebarGroup = {
  title: string
  links: SidebarLink[]
}

/**
 * Left-nav, grouped into HUD sections. The flat `SIDEBAR_LINKS` export below is
 * derived from this and kept for the command bar + tests, so add links here.
 */
export const SIDEBAR_GROUPS: SidebarGroup[] = [
  {
    title: 'Workspace',
    links: [
      { to: '/', label: 'Chat', icon: '🏠', tourId: 'nav-chat' },
      { to: '/conversations', label: 'Conversations', icon: '💬' },
      { to: '/dashboard', label: 'Dashboard', icon: '📊' },
      { to: '/runs', label: 'Runs', icon: '▶', tourId: 'nav-runs' },
      { to: '/project', label: 'Project', icon: '📁', tourId: 'nav-project' },
      { to: '/staff', label: 'Staff', icon: '👥' },
      { to: '/work-issues', label: 'Work Issues', icon: '📌' },
    ],
  },
  {
    title: 'Intelligence',
    links: [
      { to: '/agents', label: 'Agents', icon: '🤖' },
      { to: '/personas', label: 'Personas', icon: '🎭', tourId: 'nav-personas' },
      { to: '/skills', label: 'Skills', icon: '⚡' },
      { to: '/memory', label: 'Memory & Brain', icon: '🧠' },
      { to: '/knowledge', label: 'Knowledge', icon: '📚' },
      { to: '/skills-graph', label: 'Skills Graph', icon: '📈' },
    ],
  },
  {
    title: 'Insights',
    links: [
      { to: '/data', label: 'Data', icon: '🗄' },
      { to: '/logs', label: 'Logs', icon: '📋' },
      { to: '/usage', label: 'Usage', icon: '💰' },
      { to: '/feedback', label: 'Feedback', icon: '💬' },
    ],
  },
  {
    title: 'System',
    links: [
      { to: '/plugins', label: 'Plugins', icon: '🔌' },
      { to: '/soul', label: 'Soul', icon: '✨' },
      { to: '/settings/orchestrator', label: 'Orchestrator', icon: '🧭' },
      { to: '/settings/models', label: 'Settings', icon: '⚙', tourId: 'nav-settings' },
    ],
  },
]

/** Flat list (command bar + tests). Derived from SIDEBAR_GROUPS — edit groups above. */
export const SIDEBAR_LINKS: SidebarLink[] = SIDEBAR_GROUPS.flatMap(group => group.links)
