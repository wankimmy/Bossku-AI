import { describe, expect, it } from 'vitest'
import { AGENT_ROLE_DEFS, defaultProviderForRole } from '../composables/useRoleModelDefaults'

describe('useRoleModelDefaults', () => {
  it('defines core agent roles', () => {
    const roles = AGENT_ROLE_DEFS.map(d => d.role)
    expect(roles).toContain('orchestrator')
    expect(roles).toContain('executor')
    expect(roles).toContain('auditor')
  })

  it('picks first configured provider with recommendations', () => {
    const groups = [
      { provider: 'anthropic', configured: false, recommended_models: [] },
      { provider: 'ollama-cloud', configured: true, recommended_models: [{ id: 'kimi-k2.6:cloud' }] },
    ]
    expect(defaultProviderForRole(groups, 'orchestrator')).toBe('ollama-cloud')
  })
})
