import { useState, useEffect } from 'react'
import type { OfficeState } from '../office/engine/officeState.js'
import type { SubagentCharacter } from '../hooks/useExtensionMessages.js'
import type { ToolActivity } from '../office/types.js'
import { TILE_SIZE, CharacterState } from '../office/types.js'

const AGENT_ROLE_NAMES: Record<number, string> = {
  1: 'Orchestrator',
  2: 'Executor',
  3: 'Auditor',
  4: 'Security',
  5: 'Reviewer',
  6: 'Memory',
  7: 'Tools',
}

function getActivityText(agentId: number, agentTools: Record<number, ToolActivity[]>, isActive: boolean): string | null {
  const tools = agentTools[agentId]
  if (!tools || tools.length === 0) return null
  const activeTool = [...tools].reverse().find((t) => !t.done)
  if (activeTool) {
    if (activeTool.permissionWait) return 'Needs approval'
    return activeTool.status
  }
  if (isActive) {
    const last = tools[tools.length - 1]
    return last ? last.status : null
  }
  return null
}

function truncate(text: string, max: number): string {
  return text.length > max ? text.slice(0, max - 1) + '…' : text
}

interface AgentLabelsProps {
  officeState: OfficeState
  agents: number[]
  agentStatuses: Record<number, string>
  agentTools: Record<number, ToolActivity[]>
  containerRef: React.RefObject<HTMLDivElement | null>
  zoom: number
  panRef: React.RefObject<{ x: number; y: number }>
  subagentCharacters: SubagentCharacter[]
}

export function AgentLabels({
  officeState,
  agents,
  agentStatuses,
  agentTools,
  containerRef,
  zoom,
  panRef,
  subagentCharacters,
}: AgentLabelsProps) {
  const [, setTick] = useState(0)
  useEffect(() => {
    let rafId = 0
    const tick = () => {
      setTick((n) => n + 1)
      rafId = requestAnimationFrame(tick)
    }
    rafId = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(rafId)
  }, [])

  const el = containerRef.current
  if (!el) return null
  const rect = el.getBoundingClientRect()
  const dpr = window.devicePixelRatio || 1
  const canvasW = Math.round(rect.width * dpr)
  const canvasH = Math.round(rect.height * dpr)
  const layout = officeState.getLayout()
  const mapW = layout.cols * TILE_SIZE * zoom
  const mapH = layout.rows * TILE_SIZE * zoom
  const deviceOffsetX = Math.floor((canvasW - mapW) / 2) + Math.round(panRef.current.x)
  const deviceOffsetY = Math.floor((canvasH - mapH) / 2) + Math.round(panRef.current.y)

  const subLabelMap = new Map<number, string>()
  for (const sub of subagentCharacters) {
    subLabelMap.set(sub.id, sub.label)
  }

  const allIds = [...agents, ...subagentCharacters.map((s) => s.id)]

  return (
    <>
      {allIds.map((id) => {
        const ch = officeState.characters.get(id)
        if (!ch) return null

        const sittingOffset = ch.state === CharacterState.TYPE ? 6 : 0
        const screenX = (deviceOffsetX + ch.x * zoom) / dpr
        const screenY = (deviceOffsetY + (ch.y + sittingOffset - 24) * zoom) / dpr

        const status = agentStatuses[id]
        const isWaiting = status === 'waiting'
        const isActive = ch.isActive
        const isSub = ch.isSubagent

        const labelText = isSub
          ? (subLabelMap.get(id) ?? 'Subtask')
          : (AGENT_ROLE_NAMES[id] ?? `Agent #${id}`)

        const activityText = isSub ? null : getActivityText(id, agentTools, isActive)

        let dotColor = 'transparent'
        if (isWaiting) dotColor = '#cca700'
        else if (isActive) dotColor = '#3794ff'

        return (
          <div
            key={id}
            style={{
              position: 'absolute',
              left: screenX,
              top: screenY - 16,
              transform: 'translateX(-50%)',
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              pointerEvents: 'none',
              zIndex: 40,
              gap: 2,
            }}
          >
            {/* Speech bubble — white bg, black text, always visible when active */}
            {activityText && (
              <div style={{ position: 'relative', marginBottom: 3 }}>
                <div
                  style={{
                    background: '#ffffff',
                    border: '1px solid #bbb',
                    borderRadius: 6,
                    padding: '3px 7px',
                    fontSize: 10,
                    fontWeight: 500,
                    color: '#111',
                    whiteSpace: 'nowrap',
                    maxWidth: 180,
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    boxShadow: '0 2px 6px rgba(0,0,0,0.35)',
                    lineHeight: 1.4,
                  }}
                >
                  {truncate(activityText, 32)}
                </div>
                {/* Triangle pointer */}
                <div
                  style={{
                    position: 'absolute',
                    bottom: -5,
                    left: '50%',
                    transform: 'translateX(-50%)',
                    width: 0,
                    height: 0,
                    borderLeft: '5px solid transparent',
                    borderRight: '5px solid transparent',
                    borderTop: '5px solid #ffffff',
                    filter: 'drop-shadow(0 1px 1px rgba(0,0,0,0.2))',
                  }}
                />
              </div>
            )}

            {/* Name badge with status dot */}
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 3,
                background: 'rgba(20,20,30,0.88)',
                border: '1px solid rgba(255,255,255,0.15)',
                borderRadius: 3,
                padding: isSub ? '1px 3px' : '2px 6px',
                marginTop: activityText ? 6 : 0,
              }}
            >
              {dotColor !== 'transparent' && (
                <span
                  className={isActive && !isWaiting ? 'zep-agents-pulse' : undefined}
                  style={{
                    width: 5,
                    height: 5,
                    borderRadius: '50%',
                    background: dotColor,
                    flexShrink: 0,
                  }}
                />
              )}
              <span
                style={{
                  fontSize: isSub ? 9 : 10,
                  fontStyle: isSub ? 'italic' : undefined,
                  fontWeight: isSub ? undefined : 700,
                  color: isActive ? '#ffffff' : 'rgba(200,210,230,0.7)',
                  whiteSpace: 'nowrap',
                  maxWidth: isSub ? 100 : 90,
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  letterSpacing: '0.03em',
                  textTransform: isSub ? undefined : 'uppercase',
                }}
              >
                {labelText}
              </span>
            </div>
          </div>
        )
      })}
    </>
  )
}
