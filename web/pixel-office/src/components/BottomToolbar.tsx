import { useCallback, useEffect, useRef, useState } from 'react'
import { SettingsModal } from './SettingsModal.js'
import { bossku } from '../bosskuApi.js'

interface BottomToolbarProps {
  isEditMode: boolean
  onToggleEditMode: () => void
  isDebugMode: boolean
  onToggleDebugMode: () => void
}

const menuBtnStyle: React.CSSProperties = {
  width: 32,
  height: 32,
  padding: 0,
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  color: 'var(--pixel-text)',
  background: 'var(--pixel-bg)',
  border: '2px solid var(--pixel-border)',
  borderRadius: 0,
  cursor: 'pointer',
  boxShadow: 'var(--pixel-shadow)',
}

const menuBtnActive: React.CSSProperties = {
  ...menuBtnStyle,
  background: 'var(--pixel-active-bg)',
  border: '2px solid var(--pixel-accent)',
}

const dropdownStyle: React.CSSProperties = {
  position: 'absolute',
  top: 44,
  right: 0,
  minWidth: 140,
  display: 'flex',
  flexDirection: 'column',
  gap: 2,
  padding: '4px',
  background: 'var(--pixel-bg)',
  border: '2px solid var(--pixel-border)',
  borderRadius: 0,
  boxShadow: 'var(--pixel-shadow)',
}

const itemBase: React.CSSProperties = {
  width: '100%',
  padding: '4px 8px',
  fontSize: '15px',
  lineHeight: 1.3,
  textAlign: 'left',
  color: 'var(--pixel-text)',
  background: 'var(--pixel-btn-bg)',
  border: '2px solid transparent',
  borderRadius: 0,
  cursor: 'pointer',
}

const itemActive: React.CSSProperties = {
  ...itemBase,
  background: 'var(--pixel-active-bg)',
  border: '2px solid var(--pixel-accent)',
}

function HamburgerIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
      <rect x="2" y="3" width="12" height="2" fill="currentColor" />
      <rect x="2" y="7" width="12" height="2" fill="currentColor" />
      <rect x="2" y="11" width="12" height="2" fill="currentColor" />
    </svg>
  )
}

export function BottomToolbar({
  isEditMode,
  onToggleEditMode,
  isDebugMode,
  onToggleDebugMode,
}: BottomToolbarProps) {
  const rootRef = useRef<HTMLDivElement>(null)
  const [menuOpen, setMenuOpen] = useState(false)
  const [hovered, setHovered] = useState<string | null>(null)
  const [isSettingsOpen, setIsSettingsOpen] = useState(false)

  const closeMenu = useCallback(() => setMenuOpen(false), [])

  useEffect(() => {
    if (!menuOpen) return

    const onPointerDown = (e: MouseEvent) => {
      const root = rootRef.current
      if (root && !root.contains(e.target as Node)) {
        setMenuOpen(false)
      }
    }

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [menuOpen])

  const itemStyle = (id: string, active: boolean): React.CSSProperties => {
    if (active) return itemActive
    return {
      ...itemBase,
      background: hovered === id ? 'var(--pixel-btn-hover-bg)' : itemBase.background,
    }
  }

  return (
    <>
      <div
        ref={rootRef}
        data-testid="office-top-menu"
        style={{
          position: 'absolute',
          top: 8,
          right: 8,
          zIndex: 'var(--pixel-controls-z)',
        }}
      >
        <button
          type="button"
          aria-label="Office menu"
          aria-expanded={menuOpen}
          aria-haspopup="menu"
          onClick={() => setMenuOpen((v) => !v)}
          style={menuOpen ? menuBtnActive : menuBtnStyle}
          title="Office menu"
        >
          <HamburgerIcon />
        </button>

        {menuOpen && (
          <div role="menu" style={dropdownStyle} data-testid="office-top-menu-dropdown">
            <button
              type="button"
              role="menuitem"
              style={itemStyle('expand', false)}
              onMouseEnter={() => setHovered('expand')}
              onMouseLeave={() => setHovered(null)}
              onClick={() => {
                bossku.postMessage({ type: 'requestExpand' })
                closeMenu()
              }}
            >
              Expand
            </button>
            <button
              type="button"
              role="menuitem"
              style={itemStyle('layout', isEditMode)}
              onMouseEnter={() => setHovered('layout')}
              onMouseLeave={() => setHovered(null)}
              onClick={() => {
                onToggleEditMode()
                closeMenu()
              }}
            >
              Layout
            </button>
            <button
              type="button"
              role="menuitem"
              style={itemStyle('settings', isSettingsOpen)}
              onMouseEnter={() => setHovered('settings')}
              onMouseLeave={() => setHovered(null)}
              onClick={() => {
                setIsSettingsOpen(true)
                closeMenu()
              }}
            >
              Settings
            </button>
          </div>
        )}
      </div>

      <SettingsModal
        isOpen={isSettingsOpen}
        onClose={() => setIsSettingsOpen(false)}
        isDebugMode={isDebugMode}
        onToggleDebugMode={onToggleDebugMode}
      />
    </>
  )
}
