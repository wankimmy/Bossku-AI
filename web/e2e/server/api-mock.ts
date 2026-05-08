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

  if (pathname === '/api/runs/stream' && method === 'GET') {
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
