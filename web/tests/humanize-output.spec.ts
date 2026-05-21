import { describe, expect, it } from 'vitest'
import {
  formatAgentStepOutput,
  parseRiskItems,
  summarizeRouter,
  formatRouterDisplay,
} from '../utils/humanizeOutput'

describe('humanizeOutput', () => {
  it('parses JSON risk lines into structured items', () => {
    const risks = parseRiskItems([
      '{"issue":"Missing rate limiting","severity":"medium","location":"routes/web.php","description":"Public endpoints lack rate limiting."}',
    ])
    expect(risks).toHaveLength(1)
    expect(risks[0].issue).toBe('Missing rate limiting')
    expect(risks[0].severity).toBe('medium')
    expect(risks[0].location).toBe('routes/web.php')
  })

  it('formats router JSON as readable summary', () => {
    const raw = JSON.stringify({
      primary_skill: { name: 'bosskuai-codebase-analysis', reason: 'Keyword match.' },
      secondary_skills: [{ name: 'bosskuai-project-understanding', reason: 'Second score.' }],
      rules: [{ name: 'Activation' }],
      playbooks: [],
      checklists: [],
    })
    const out = formatAgentStepOutput('router', raw)
    expect(out.summary).toContain('bosskuai-codebase-analysis')
    expect(out.router?.primarySkill).toBe('bosskuai-codebase-analysis')
    expect(out.router?.rulesCount).toBe(1)
    expect(summarizeRouter(out.router!)).toContain('+1 secondary')
  })

  it('formats security auditor JSON with issues list', () => {
    const raw = JSON.stringify({
      status: 'revise',
      summary: 'Several security gaps found.',
      security_issues: [
        { severity: 'high', issue: 'Webhook verification missing', recommendation: 'Verify signatures' },
      ],
    })
    const out = formatAgentStepOutput('security-auditor', raw)
    expect(out.summary).toContain('security')
    expect(out.risks).toHaveLength(1)
    expect(out.risks![0].issue).toContain('Webhook')
  })
})
