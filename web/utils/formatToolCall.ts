type UnknownRecord = Record<string, unknown>

export interface ToolCallEventLike {
  type?: unknown
  tool?: unknown
  payload?: unknown
  summary?: unknown
  message?: unknown
  status?: unknown
}

export function isToolCallEvent(evt: ToolCallEventLike): boolean {
  return String(evt.type ?? '') === 'tool_call'
}

/** Short label for timeline headers (e.g. "Read file", "Search repo"). */
export function formatToolCallTitle(evt: ToolCallEventLike): string {
  const tool = String(evt.tool ?? '')
  switch (tool) {
    case 'file_read_safe':
      return 'Read file'
    case 'file_search':
      return 'Search repo'
    case 'file_glob':
      return 'List files'
    case 'file_write_proposed':
      return 'Propose write'
    case 'db_query':
      return 'SQL query'
    case 'log':
      return 'Log'
    default:
      return tool ? tool.replaceAll('_', ' ') : 'Tool'
  }
}

/** One-line description of what the agent is doing. */
export function formatToolCallSummary(evt: ToolCallEventLike): string {
  const existing = stringOrEmpty(evt.summary ?? evt.message)
  if (existing) return existing

  const tool = String(evt.tool ?? 'tool')
  const payload = asRecord(evt.payload) ?? {}
  const status = String(evt.status ?? 'ok')
  const detail = actionDetail(tool, payload)
  const suffix = status === 'error' ? ' (failed)' : status === 'blocked' ? ' (blocked)' : ''

  return detail + suffix
}

function actionDetail(tool: string, payload: UnknownRecord): string {
  switch (tool) {
    case 'file_read_safe':
      return `Reading file: ${path(payload)}`
    case 'file_search':
      return `Searching repo for "${query(payload)}"${glob(payload) !== '*' ? ` in ${glob(payload)}` : ''}`
    case 'file_glob':
      return `Listing files: ${pattern(payload)}`
    case 'file_write_proposed':
      return `Proposing file write: ${path(payload)}`
    case 'db_query':
      return `Running read-only SQL: ${sqlPreview(payload)}`
    case 'log':
      return `Log: ${truncate(stringOrEmpty(payload.message), 120) || '(empty)'}`
    default:
      return `Tool: ${tool}`
  }
}

function path(payload: UnknownRecord): string {
  return stringOrEmpty(payload.path) || '(path missing)'
}

function query(payload: UnknownRecord): string {
  return truncate(stringOrEmpty(payload.q ?? payload.query), 80) || '(empty)'
}

function glob(payload: UnknownRecord): string {
  return stringOrEmpty(payload.glob) || '*'
}

function pattern(payload: UnknownRecord): string {
  return truncate(stringOrEmpty(payload.pattern ?? payload.glob), 120) || '(pattern missing)'
}

function sqlPreview(payload: UnknownRecord): string {
  const sql = stringOrEmpty(payload.sql).replace(/\s+/g, ' ').trim()
  return truncate(sql, 160) || '(empty query)'
}

function truncate(text: string, max: number): string {
  if (text.length <= max) return text
  return `${text.slice(0, max - 1)}…`
}

function stringOrEmpty(value: unknown): string {
  if (value === undefined || value === null) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  if (Array.isArray(value)) {
    return value.map(item => (typeof item === 'string' ? item : JSON.stringify(item))).join('\n')
  }
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value)
    }
    catch {
      return ''
    }
  }
  return String(value)
}

function asRecord(value: unknown): UnknownRecord | undefined {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? value as UnknownRecord
    : undefined
}
