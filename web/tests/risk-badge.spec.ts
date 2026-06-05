import { describe, expect, it } from 'vitest'

type RiskLevel = 'low' | 'medium' | 'high' | 'critical'

function riskColor(level: RiskLevel): string {
  const map: Record<RiskLevel, string> = {
    low: 'green',
    medium: 'yellow',
    high: 'orange',
    critical: 'red',
  }
  return map[level] ?? 'gray'
}

describe('RiskBadge color logic', () => {
  it('low risk = green', () => expect(riskColor('low')).toBe('green'))
  it('medium risk = yellow', () => expect(riskColor('medium')).toBe('yellow'))
  it('high risk = orange', () => expect(riskColor('high')).toBe('orange'))
  it('critical risk = red', () => expect(riskColor('critical')).toBe('red'))
})
