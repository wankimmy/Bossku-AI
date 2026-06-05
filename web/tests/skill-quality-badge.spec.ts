import { describe, expect, it } from 'vitest'

function qualityColor(score: number): string {
  if (score >= 70) return 'green'
  if (score >= 40) return 'yellow'
  return 'red'
}

function qualityLabel(score: number): string {
  if (score >= 70) return 'Good'
  if (score >= 40) return 'Fair'
  return 'Weak'
}

describe('SkillQualityBadge logic', () => {
  it('score >= 70 is green / Good', () => {
    expect(qualityColor(82)).toBe('green')
    expect(qualityLabel(82)).toBe('Good')
    expect(qualityColor(70)).toBe('green')
  })

  it('score 40–69 is yellow / Fair', () => {
    expect(qualityColor(55)).toBe('yellow')
    expect(qualityLabel(55)).toBe('Fair')
    expect(qualityColor(40)).toBe('yellow')
  })

  it('score < 40 is red / Weak', () => {
    expect(qualityColor(35)).toBe('red')
    expect(qualityLabel(35)).toBe('Weak')
    expect(qualityColor(0)).toBe('red')
  })

  it('boundary: 69 is yellow, 70 is green', () => {
    expect(qualityColor(69)).toBe('yellow')
    expect(qualityColor(70)).toBe('green')
  })
})
