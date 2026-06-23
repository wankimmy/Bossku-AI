import { describe, expect, it } from 'vitest'
import {
  assessFileChange,
  computeFileChangeStats,
  isPlaceholderText,
  validateFileChange,
} from './approvalReview'

describe('approvalReview', () => {
  it('detects placeholder text', () => {
    expect(isPlaceholderText('Will be determined after reading the file')).toBe(true)
    expect(isPlaceholderText("<?php\nclass Foo {}\n")).toBe(false)
    expect(isPlaceholderText('changed')).toBe(false)
  })

  it('allows long real content that mentions placeholders', () => {
    const content = `# Implementation Status

| Feature | Status | Notes |
|---|---|---|
| Save / Load | Implemented | Local storage persistence replaced the placeholder row. |
${Array.from({ length: 12 }, (_, i) => `| Detail ${i} | Implemented | Concrete implementation note ${i}. |`).join('\n')}
`

    expect(isPlaceholderText(content)).toBe(false)
  })

  it('blocks destructive wipe', () => {
    const before = Array.from({ length: 30 }, (_, i) => `line ${i}`).join('\n')
    const reason = validateFileChange(before, 'Will be determined after reading the file', 'modified', 'app/Foo.php')
    expect(reason).not.toBeNull()
  })

  it('computes line stats', () => {
    const stats = computeFileChangeStats('a\nb', 'a\nc')
    expect(stats.removed).toBe(1)
    expect(stats.added).toBe(1)
  })

  it('assessFileChange blocks placeholder evidence', () => {
    const before = Array.from({ length: 25 }, (_, i) => `// ${i}`).join('\n')
    const result = assessFileChange({
      path: 'app/ReceiptController.php',
      change_type: 'modified',
      before,
      after: 'Will be determined after reading the file',
    })
    expect(result.blocked).toBe(true)
    expect(result.reason).toBeTruthy()
  })
})
