/**
 * Lightweight markdown-to-HTML renderer.
 * Handles: ## headings, - bullet lists, **bold**, `inline code`, plain paragraphs.
 * All text content is HTML-escaped before rendering to prevent XSS.
 */
function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/** Apply inline formatting: **bold**, *italic*, `code` */
function applyInline(text: string): string {
  const safe = escapeHtml(text)
  return safe
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>')
}

export function renderMarkdown(raw: string): string {
  const lines = raw.split('\n')
  const parts: string[] = []
  let i = 0

  while (i < lines.length) {
    const line = lines[i]
    const trimmed = line.trim()

    // ## Heading (h2/h3)
    const h2 = trimmed.match(/^##\s+(.+)$/)
    if (h2) {
      parts.push(`<h3 class="md-h3">${applyInline(h2[1])}</h3>`)
      i++
      continue
    }

    // # Heading (h1)
    const h1 = trimmed.match(/^#\s+(.+)$/)
    if (h1) {
      parts.push(`<h2 class="md-h2">${applyInline(h1[1])}</h2>`)
      i++
      continue
    }

    // Bullet list: collect consecutive - lines
    if (/^[-*]\s/.test(trimmed)) {
      const items: string[] = []
      while (i < lines.length && /^[-*]\s/.test(lines[i].trim())) {
        const itemText = lines[i].trim().replace(/^[-*]\s+/, '')
        items.push(`<li>${applyInline(itemText)}</li>`)
        i++
      }
      parts.push(`<ul class="md-ul">${items.join('')}</ul>`)
      continue
    }

    // Blank line
    if (trimmed === '') {
      parts.push('<div class="md-spacer"></div>')
      i++
      continue
    }

    // Plain paragraph
    parts.push(`<p class="md-p">${applyInline(trimmed)}</p>`)
    i++
  }

  return parts.join('')
}
