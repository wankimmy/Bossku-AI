/**
 * Hermetic mock of BosskuAI Laravel API (see app/routes/api.php).
 * Playwright starts this before Nuxt; set MOCK_PORT (default 8001).
 */
import { readFileSync } from 'node:fs'
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturesDir = join(__dirname, '../fixtures')

function load<T>(name: string): T {
  const raw = readFileSync(join(fixturesDir, name), 'utf8')
  return JSON.parse(raw) as T
}

const runsList = load<{ data: Record<string, unknown>[] }>('runs-list.json')
const runDetailR1 = load<Record<string, unknown>>('run-detail-r1.json')
const skillsList = load<{ data: Record<string, unknown>[] }>('skills-list.json')
const skillSk1 = load<Record<string, unknown>>('skill-sk1.json')
const rulesList = load<{ data: Record<string, unknown>[] }>('rules-list.json')
const playbooksList = load<{ data: Record<string, unknown>[] }>('playbooks-list.json')
const playbookPb1 = load<Record<string, unknown>>('playbook-pb1.json')
const checklistsList = load<{ data: Record<string, unknown>[] }>('checklists-list.json')
const checklistCl1 = load<Record<string, unknown>>('checklist-cl1.json')
const memoryList = load<{ data: Record<string, unknown>[] }>('memory-list.json')
const memorySearchHit = load<unknown[]>('memory-search-hit.json')
const defaultSettings = load<Record<string, string>>('settings-public.json')

let settingsState: Record<string, string> = { ...defaultSettings }
let lastSettingsPut: Record<string, unknown> | null = null

function cors(res: ServerResponse) {
  res.setHeader('Access-Control-Allow-Origin', '*')
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept')
}

function json(res: ServerResponse, body: unknown, code = 200) {
  cors(res)
  res.writeHead(code, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(body))
}

function readBody(req: IncomingMessage): Promise<string> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = []
    req.on('data', c => chunks.push(c as Buffer))
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')))
    req.on('error', reject)
  })
}

async function handle(req: IncomingMessage, res: ServerResponse) {
  const url = new URL(req.url ?? '/', `http://127.0.0.1`)
  const pathname = url.pathname.replace(/\/+$/, '') || '/'
  const method = (req.method ?? 'GET').toUpperCase()

  if (method === 'OPTIONS') {
    cors(res)
    res.writeHead(204).end()
    return
  }

  if (pathname === '/api/__e2e/reset' && method === 'POST') {
    settingsState = { ...defaultSettings }
    lastSettingsPut = null
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/__e2e/last-settings-put' && method === 'GET') {
    json(res, lastSettingsPut)
    return
  }

  if (pathname === '/api/runs/stream' && (method === 'GET' || method === 'POST')) {
    const prompt = url.searchParams.get('prompt') ?? ''
    cors(res)
    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive',
      'X-Accel-Buffering': 'no',
    })

    const sse = (obj: Record<string, unknown>) => {
      res.write(`data: ${JSON.stringify(obj)}\n\n`)
    }

    sse({
      type: 'memory_loaded',
      status: 'ok',
      snippets: ['fixture-memory'],
    })

    sse({
      type: 'routing_decision',
      status: 'ok',
      routing_decision: {
        workflow: 'direct_answer',
        task_type: 'question',
        risk_level: 'low',
        skill: 'laravel',
        executor_profile: 'backend',
        needs_executor: false,
        memory_mode: 'retrieve',
      },
    })

    sse({
      type: 'executor_chunk',
      status: 'ok',
      delta: 'fixture chunk…',
    })

    sse({
      type: 'run_completed',
      status: 'ok',
      output: `Mock stream done for: ${prompt.slice(0, 80)}`,
    })

    res.end()
    return
  }

  if (pathname === '/api/runs' && method === 'GET') {
    json(res, runsList)
    return
  }

  if (pathname === '/api/runs' && method === 'POST') {
    const raw = await readBody(req)
    let prompt = ''
    try {
      prompt = String(JSON.parse(raw || '{}').prompt ?? '')
    }
    catch {
      //
    }
    json(res, {
      final_output: `Mock sync completed · ${prompt.slice(0, 120) || '(empty)'}`,
      status: 'done',
    })
    return
  }

  if (pathname === '/api/runs/r_1' && method === 'GET') {
    json(res, runDetailR1)
    return
  }

  if (pathname === '/api/skills' && method === 'GET') {
    const q = (url.searchParams.get('q') ?? '').toLowerCase().trim()
    if (!q) {
      json(res, skillsList)
      return
    }
    const filtered = skillsList.data.filter(
      s =>
        String(s.name ?? '').toLowerCase().includes(q)
        || String(s.description ?? '').toLowerCase().includes(q),
    )
    json(res, { ...skillsList, data: filtered })
    return
  }

  if (pathname === '/api/skills/sk_1' && method === 'GET') {
    json(res, skillSk1)
    return
  }

  if (pathname === '/api/skills/sk_1' && method === 'PATCH') {
    json(res, { ...skillSk1, ...(JSON.parse(await readBody(req) || '{}') as object) })
    return
  }

  if (pathname === '/api/rules' && method === 'GET') {
    json(res, rulesList)
    return
  }

  if (pathname.startsWith('/api/rules/') && method === 'PATCH') {
    json(res, { ok: true, id: pathname.split('/').pop() })
    return
  }

  if (pathname === '/api/playbooks' && method === 'GET') {
    json(res, playbooksList)
    return
  }

  if (pathname === '/api/playbooks/pb_1' && method === 'GET') {
    json(res, playbookPb1)
    return
  }

  if (pathname === '/api/checklists' && method === 'GET') {
    json(res, checklistsList)
    return
  }

  if (pathname === '/api/checklists/cl_1' && method === 'GET') {
    json(res, checklistCl1)
    return
  }

  if (pathname === '/api/memory' && method === 'GET') {
    json(res, memoryList)
    return
  }

  if (pathname === '/api/memory/search' && method === 'POST') {
    json(res, memorySearchHit)
    return
  }

  if (pathname.startsWith('/api/memory/') && method === 'PATCH') {
    json(res, { ok: true })
    return
  }

  if (pathname.startsWith('/api/memory/') && method === 'DELETE') {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/settings' && method === 'GET') {
    json(res, { ...settingsState })
    return
  }

  if (pathname === '/api/settings' && method === 'PUT') {
    try {
      const body = JSON.parse(await readBody(req) || '{}') as Record<string, unknown>
      lastSettingsPut = body
      for (const [k, v] of Object.entries(body)) {
        if (v !== undefined && v !== null) settingsState[k] = String(v)
      }
    }
    catch {
      lastSettingsPut = null
    }
    json(res, { ...settingsState })
    return
  }

  if (pathname === '/api/knowledge/import' && method === 'POST') {
    json(res, { imported: 1, skills: 1, playbooks: 0 })
    return
  }

  // ── New spec endpoints ────────────────────────────────────────────────────

  if (pathname === '/api/dashboard' && method === 'GET') {
    json(res, {
      stats: { total_runs: 5, runs_today: 2, active_runs: 0, skills_count: 5, memory_count: 4 },
      recent_runs: [],
      agent_statuses: [],
    })
    return
  }

  if (pathname === '/api/agents' && method === 'GET') {
    json(res, { data: [
      { role: 'planner', run_count: 5, avg_latency_ms: 1200, last_used_at: null },
      { role: 'coder', run_count: 5, avg_latency_ms: 3400, last_used_at: null },
      { role: 'auditor', run_count: 5, avg_latency_ms: 900, last_used_at: null },
    ]})
    return
  }

  if (pathname.startsWith('/api/agents/') && method === 'GET') {
    json(res, { role: pathname.split('/').pop(), run_count: 0, avg_latency_ms: 0 })
    return
  }

  if (pathname === '/api/logs' && method === 'GET') {
    json(res, { data: [], meta: { total: 0, current_page: 1 } })
    return
  }

  if (pathname === '/api/usage' && method === 'GET') {
    json(res, { data: [], meta: { total: 0, current_page: 1 } })
    return
  }

  if (pathname === '/api/usage/summary' && method === 'GET') {
    json(res, { total_input_tokens: 0, total_output_tokens: 0, total_cost_usd: 0, by_provider: [] })
    return
  }

  if (pathname === '/api/brain' && method === 'GET') {
    json(res, {
      learning_events: { pending: 1, accepted: 2, rejected: 0, applied: 0 },
      skill_candidates: { draft: 1, pending_review: 1, approved: 3, rejected: 0 },
      feedback_unprocessed: 2,
      memory_confidence: { avg: 0.78, min: 0.3, max: 1.0 },
      conflict_count: 1,
    })
    return
  }

  if (pathname === '/api/learning' && method === 'GET') {
    json(res, { data: [] })
    return
  }

  if (pathname.startsWith('/api/learning/') && method === 'POST') {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/feedback' && method === 'GET') {
    json(res, { data: [] })
    return
  }

  if (pathname === '/api/feedback' && method === 'POST') {
    json(res, { id: 'fb_mock', signal: 'thumbs_up' }, 201)
    return
  }

  if (pathname.startsWith('/api/feedback/') && method === 'GET') {
    json(res, { thumbs_up: 1, thumbs_down: 0, avg_rating: null, count: 1 })
    return
  }

  if (pathname === '/api/skill-candidates' && method === 'GET') {
    json(res, { data: [] })
    return
  }

  if (pathname.startsWith('/api/skill-candidates/') && (method === 'POST' || method === 'PATCH')) {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/plugins' && method === 'GET') {
    json(res, { data: [] })
    return
  }

  if (pathname.startsWith('/api/plugins/') && method === 'POST') {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/soul' && method === 'GET') {
    json(res, { id: 'soul_1', version: 'v1.0.0', content: '# BosskuAI Soul v1.0.0\n\n## Identity\nBosskuAI is a self-learning developer AI orchestrator.', active: true })
    return
  }

  if (pathname === '/api/soul/history' && method === 'GET') {
    json(res, [{ id: 'soul_1', version: 'v1.0.0', active: true, change_summary: 'Initial', created_at: new Date().toISOString() }])
    return
  }

  if (pathname === '/api/soul/suggestions' && method === 'GET') {
    json(res, [])
    return
  }

  if (pathname === '/api/soul' && method === 'PUT') {
    json(res, { id: 'soul_2', version: 'v1.0.1', active: true })
    return
  }

  const mockWorkspaceGraph = {
    version: 'mock-1',
    node_count: 2,
    edge_count: 1,
    nodes: [
      {
        id: 'cofounder',
        label: 'cofounder',
        category: 'operating',
        depth: 'DEEP',
        is_marquee: true,
        triggers: ['cofounder mode'],
        keywords: ['startup'],
        trigger_count: 1,
        description: 'Expert cofounder skill.',
        skill_lines: 120,
        playbook_lines: 200,
        total_lines: 320,
        playbook_refs: ['cofounder-playbook.md'],
      },
      {
        id: 'bosskuai-laravel-development',
        label: 'laravel-development',
        category: 'engineering',
        depth: 'OK',
        is_marquee: true,
        triggers: ['laravel'],
        keywords: ['php'],
        trigger_count: 1,
        description: 'Laravel delivery.',
        skill_lines: 80,
        playbook_lines: 40,
        total_lines: 120,
        playbook_refs: [],
      },
    ],
    edges: [{ source: 'cofounder', target: 'bosskuai-laravel-development', kind: 'cross_ref' }],
  }

  if (pathname === '/api/workspace/graph' && method === 'GET') {
    json(res, mockWorkspaceGraph)
    return
  }

  if (pathname === '/api/knowledge-graph' && method === 'GET') {
    json(res, {
      ...mockWorkspaceGraph,
      version: 'knowledge-db',
      nodes: mockWorkspaceGraph.nodes.map(n => ({
        ...n,
        type: 'skill',
        source_type: 'skill',
        source_id: n.id,
      })),
      edges: [{ source: 'n1', target: 'n2', kind: 'used_in' }],
    })
    return
  }

  if (pathname === '/api/knowledge-graph/rebuild' && method === 'POST') {
    json(res, { message: 'Knowledge graph rebuilt.', node_count: 2, edge_count: 1 })
    return
  }

  if (pathname === '/api/skills-graph' && method === 'GET') {
    json(res, mockWorkspaceGraph)
    return
  }

  if (pathname === '/api/skills-graph/rebuild' && method === 'POST') {
    json(res, { nodes: 0, edges: 0 })
    return
  }

  if (pathname === '/api/providers' && method === 'GET') {
    json(res, { data: [
      { id: 'p_1', name: 'Ollama (Local)', slug: 'ollama-local', type: 'ollama', health_status: 'healthy', is_active: true },
    ]})
    return
  }

  if (pathname.startsWith('/api/providers') && (method === 'POST' || method === 'PATCH' || method === 'DELETE')) {
    json(res, { ok: true })
    return
  }

  if (pathname.startsWith('/api/providers/') && method === 'POST') {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/model-routes' && method === 'GET') {
    json(res, { data: [
      { id: 'mr_1', role: 'planner', primary_model: 'qwen2.5-coder:7b', is_active: true },
      { id: 'mr_2', role: 'coder', primary_model: 'qwen2.5-coder:7b', is_active: true },
    ]})
    return
  }

  if (pathname.startsWith('/api/model-routes') && (method === 'POST' || method === 'PATCH' || method === 'DELETE')) {
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/approvals' && method === 'GET') {
    json(res, { data: [] })
    return
  }

  if (pathname.startsWith('/api/approvals/') && method === 'POST') {
    json(res, { ok: true })
    return
  }

  if (pathname.startsWith('/api/runs/') && pathname.endsWith('/pause') && method === 'POST') {
    json(res, { ok: true, status: 'paused' })
    return
  }

  if (pathname.startsWith('/api/runs/') && pathname.endsWith('/resume') && method === 'POST') {
    json(res, { ok: true, status: 'running' })
    return
  }

  if (pathname.startsWith('/api/runs/') && method === 'GET') {
    // Sub-resources for run detail
    if (pathname.endsWith('/timeline')) { json(res, { data: [] }); return }
    if (pathname.endsWith('/messages')) { json(res, { data: [] }); return }
    if (pathname.endsWith('/tool-calls')) { json(res, { data: [] }); return }
    if (pathname.endsWith('/file-changes')) { json(res, { data: [] }); return }
    if (pathname.endsWith('/audit')) { json(res, { data: [] }); return }
    if (pathname.endsWith('/usage')) { json(res, { data: [], total_cost: 0 }); return }
    if (pathname.endsWith('/feedback')) { json(res, { data: [] }); return }
  }

  json(res, { message: 'mock: not found', path: pathname }, 404)
}

const port = Number(process.env.MOCK_PORT ?? 8001)
const server = createServer((req, res) => {
  void handle(req, res).catch((err) => {
    console.error(err)
    cors(res)
    res.writeHead(500, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ message: String(err) }))
  })
})

server.listen(port, '0.0.0.0', () => {
  console.log(`BosskuAI API mock listening on http://127.0.0.1:${port}`)
})
