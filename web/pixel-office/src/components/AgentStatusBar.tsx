import type { ToolActivity } from '../office/types.js'

const AGENT_ROLE_NAMES: Record<number, string> = {
  1: 'Orchestrator',
  2: 'Executor',
  3: 'Auditor',
  4: 'Security',
  5: 'Reviewer',
  6: 'Memory',
  7: 'Tools',
}

const AGENT_COLORS: Record<number, string> = {
  1: '#7c6af7',
  2: '#38bdf8',
  3: '#34d399',
  4: '#fb923c',
  5: '#f472b6',
  6: '#a78bfa',
  7: '#94a3b8',
}

function getActivity(id: number, tools: Record<number, ToolActivity[]>, isActive: boolean): string {
  const list = tools[id]
  if (!list || list.length === 0) return 'Idle'
  const active = [...list].reverse().find((t) => !t.done)
  if (active) return active.permissionWait ? 'Needs approval' : active.status
  if (isActive) return list[list.length - 1]?.status ?? 'Working'
  return 'Done'
}

function truncate(s: string, max: number): string {
  return s.length > max ? s.slice(0, max - 1) + '…' : s
}

function hexToRgb(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `${r},${g},${b}`
}

interface AgentStatusBarProps {
  agents: number[]
  agentStatuses: Record<number, string>
  agentTools: Record<number, ToolActivity[]>
  isEditMode: boolean
}

export function AgentStatusBar({ agents, agentStatuses, agentTools, isEditMode }: AgentStatusBarProps) {
  if (isEditMode || agents.length === 0) return null

  return (
    <div
      style={{
        position: 'absolute',
        bottom: 36,
        left: 0,
        right: 0,
        display: 'flex',
        justifyContent: 'center',
        pointerEvents: 'none',
        zIndex: 45,
        padding: '0 8px',
      }}
    >
      <div
        style={{
          display: 'flex',
          gap: 4,
          background: 'rgba(10,10,18,0.88)',
          border: '1px solid rgba(255,255,255,0.1)',
          borderRadius: 6,
          padding: '5px 8px',
          flexWrap: 'wrap',
          justifyContent: 'center',
          maxWidth: '100%',
        }}
      >
        {agents.map((id) => {
          const status = agentStatuses[id]
          const isWaiting = status === 'waiting'
          const tools = agentTools[id]
          const hasActive = tools?.some((t) => !t.done)
          const isActive = Boolean(hasActive && status !== 'waiting')
          const accentColor = AGENT_COLORS[id] ?? '#94a3b8'
          const activity = getActivity(id, agentTools, isActive)
          const isIdle = activity === 'Idle' || activity === 'Done'

          return (
            <div
              key={id}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 5,
                padding: '3px 7px',
                borderRadius: 4,
                background: isActive
                  ? `rgba(${hexToRgb(accentColor)},0.15)`
                  : 'transparent',
                border: `1px solid ${isActive ? accentColor + '55' : 'rgba(255,255,255,0.06)'}`,
                minWidth: 0,
              }}
            >
              <span
                style={{
                  width: 6,
                  height: 6,
                  borderRadius: '50%',
                  flexShrink: 0,
                  background: isWaiting ? '#facc15' : isActive ? accentColor : 'rgba(150,150,180,0.35)',
                  boxShadow: isActive ? `0 0 4px ${accentColor}` : undefined,
                  display: 'inline-block',
                }}
              />
              <span
                style={{
                  fontSize: 9,
                  fontWeight: 700,
                  letterSpacing: '0.04em',
                  textTransform: 'uppercase',
                  color: isActive ? accentColor : 'rgba(180,190,210,0.55)',
                  flexShrink: 0,
                }}
              >
                {AGENT_ROLE_NAMES[id] ?? `#${id}`}
              </span>
              {!isIdle && (
                <span
                  style={{
                    fontSize: 9,
                    color: 'rgba(200,210,230,0.75)',
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    maxWidth: 100,
                  }}
                >
                  {truncate(activity, 18)}
                </span>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
