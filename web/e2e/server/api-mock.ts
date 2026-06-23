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
const repoRoot = join(__dirname, '../../../..').replace(/\\/g, '/')

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
const productTourPrompt = 'Review the access policy before release.'

type MockProject = {
  id: string
  name: string
  host_path: string
  container_path: string
  is_active: boolean
  created_at: string
  updated_at: string
}

type MockProjectChange = Record<string, unknown>

const workspaceMeta = {
  workspace_mount: '/repo',
  workspace_host_prefix: repoRoot,
  default_repo_root: '/repo',
}

const initialProjects: MockProject[] = [
  {
    id: 'proj_1',
    name: 'Bossku AI',
    host_path: repoRoot,
    container_path: '/repo',
    is_active: true,
    created_at: '2026-05-25T00:00:00.000000Z',
    updated_at: '2026-05-25T00:00:00.000000Z',
  },
]

const initialTree: Record<string, { name: string; path: string; type: 'dir' | 'file' }[]> = {
  '': [
    { name: 'app', path: 'app', type: 'dir' },
    { name: 'README.md', path: 'README.md', type: 'file' },
  ],
  app: [
    { name: 'main.php', path: 'app/main.php', type: 'file' },
  ],
}

const mockWorkspaceFolders: Record<string, { name: string; path: string; relative: string; has_children: boolean }[]> = {
  '': [
    { name: 'Bossku-AI', path: '/repo/Bossku-AI', relative: 'Bossku-AI', has_children: true },
    { name: 'demo-app', path: '/repo/demo-app', relative: 'demo-app', has_children: false },
  ],
  'Bossku-AI': [
    { name: 'app', path: '/repo/Bossku-AI/app', relative: 'Bossku-AI/app', has_children: false },
  ],
}

const initialFiles: Record<string, string> = {
  'README.md': '# Bossku AI\n\nMock workspace used by E2E.',
  'app/main.php': '<?php echo "Bossku AI";',
}

let settingsState: Record<string, string> = { ...defaultSettings }
let lastSettingsPut: Record<string, unknown> | null = null
let projectsState: MockProject[] = structuredClone(initialProjects)
let activeProjectId: string | null = initialProjects[0]?.id ?? null
let projectTreeState: Record<string, { name: string; path: string; type: 'dir' | 'file' }[]> = structuredClone(initialTree)
let projectFilesState: Record<string, string> = structuredClone(initialFiles)
let projectChangesState: MockProjectChange[] = []
const productTourRunIds = new Set<string>()
let knowledgeRecent = {
  data: [
    {
      id: 'k_1',
      human_summary: 'Mock knowledge article',
      content: 'Mock knowledge article body stored from an imported URL.',
      type: 'knowledge',
      source: 'url',
      tags: ['knowledge', 'research'],
      is_active: true,
      updated_at: '2026-05-25T00:00:00.000000Z',
    },
  ],
}

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

function resetProjectState() {
  projectsState = structuredClone(initialProjects)
  activeProjectId = initialProjects[0]?.id ?? null
  projectTreeState = structuredClone(initialTree)
  projectFilesState = structuredClone(initialFiles)
  projectChangesState = []
  productTourRunIds.clear()
}

function activeProject() {
  return projectsState.find(project => project.id === activeProjectId) ?? null
}

function projectListResponse() {
  return {
    projects: projectsState,
    active_project_id: activeProjectId,
    workspace: workspaceMeta,
  }
}

function projectUnderWorkspace(hostPath: string) {
  const normalized = hostPath.replace(/\\/g, '/').replace(/\/+$/, '')
  const prefix = workspaceMeta.workspace_host_prefix.replace(/\\/g, '/').replace(/\/+$/, '')
  return normalized.toLowerCase() === prefix.toLowerCase()
    || normalized.toLowerCase().startsWith(`${prefix.toLowerCase()}/`)
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
    resetProjectState()
    knowledgeRecent = {
      data: [
        {
          id: 'k_1',
          human_summary: 'Mock knowledge article',
          content: 'Mock knowledge article body stored from an imported URL.',
          type: 'knowledge',
          source: 'url',
          tags: ['knowledge', 'research'],
          is_active: true,
          updated_at: '2026-05-25T00:00:00.000000Z',
        },
      ],
    }
    json(res, { ok: true })
    return
  }

  if (pathname === '/api/__e2e/last-settings-put' && method === 'GET') {
    json(res, lastSettingsPut)
    return
  }

  if (pathname === '/api/runs/stream' && (method === 'GET' || method === 'POST')) {
    let prompt = url.searchParams.get('prompt') ?? ''
    if (method === 'POST') {
      try {
        prompt = String(JSON.parse(await readBody(req) || '{}').prompt ?? '')
      }
      catch {
        prompt = ''
      }
    }
    const runId = `run_mock_${Date.now()}`
    const trimmedPrompt = prompt.trim()
    const isSmokeChat = /^(test|ping|hello|hi|hey)\s*[!?.]*$/i.test(trimmedPrompt)
    const isLongPrompt = prompt.length > 50_000
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
      type: 'run_started',
      status: 'ok',
      run_id: runId,
    })

    if (isLongPrompt) {
      sse({
        type: 'long_prompt_materialized',
        status: 'success',
        run_id: runId,
        summary: 'Long prompt was written to temporary project files.',
        artifacts: {
          long_prompt: {
            relative_dir: `tmp/bossku-prompts/mock-${Date.now()}`,
            prompt_path: 'tmp/bossku-prompts/mock/prompt.md',
            chunk_count: Math.ceil(prompt.length / 7500),
            original_length: prompt.length,
            cleanup_status: 'pending',
          },
        },
      })
    }

    if (isSmokeChat) {
      const route = {
        workflow: 'direct_answer',
        task_type: 'question',
        risk_level: 'low',
        skill: 'generic',
        executor_profile: 'none',
        needs_executor: false,
        needs_auditor: false,
        needs_security_auditor: false,
        needs_final_reviewer: false,
        memory_mode: 'none',
      }
      const models = {
        router: 'mock-router',
        direct_answer: 'mock-direct',
      }

      sse({
        type: 'model_router_done',
        agent: 'router',
        status: 'success',
        run_id: runId,
        routing: route,
        models,
        artifacts: {
          routing_decision: route,
          models_resolved: models,
          pipeline_agents: ['direct_answer'],
          skipped_agents: ['executor', 'auditor', 'security-auditor', 'final-reviewer'],
        },
      })

      sse({
        type: 'run_completed',
        agent: 'direct_answer',
        from_agent: 'direct_answer',
        to_agent: 'system',
        status: 'success',
        run_id: runId,
        model_role: 'fast',
        model: 'mock-direct',
        routing: route,
        models,
        output: `BosskuAI is running. Your prompt "${trimmedPrompt}" was received.`,
      })

      res.end()
      return
    }

    if (trimmedPrompt === productTourPrompt) {
      productTourRunIds.add(runId)
      const route = {
        workflow: 'orchestrator_executor',
        task_type: 'code_edit',
        risk_level: 'medium',
        skill: 'laravel',
        executor_profile: 'backend',
        needs_executor: true,
        needs_auditor: true,
        needs_security_auditor: false,
        needs_final_reviewer: true,
        memory_mode: 'retrieve',
      }

      sse({
        type: 'model_router_done',
        agent: 'router',
        status: 'success',
        run_id: runId,
        routing: route,
        artifacts: {
          routing_decision: route,
          pipeline_agents: ['orchestrator', 'executor', 'auditor', 'final-reviewer'],
        },
      })
      sse({
        type: 'memory_retrieved',
        agent: 'memory',
        status: 'success',
        run_id: runId,
        message: 'Authorization rules from the active project.',
      })
      sse({ type: 'planner_started', agent: 'planner', status: 'running', run_id: runId })
      sse({
        type: 'planner_completed',
        agent: 'planner',
        status: 'success',
        run_id: runId,
        artifacts: {
          plan: {
            goal: productTourPrompt,
            task_summary: productTourPrompt,
            flow_steps: ['Trace policy usage', 'Check tenant boundaries', 'Run focused verification'],
            risk_notes: ['Authorization changes need human review before application.'],
          },
          checklist: [
            { id: 'tour-trace', title: 'Trace policy usage', owner: 'planner', status: 'completed' },
            { id: 'tour-boundary', title: 'Check tenant boundaries', owner: 'executor', status: 'in_progress' },
            { id: 'tour-verify', title: 'Run focused verification', owner: 'auditor', status: 'pending' },
          ],
        },
      })
      sse({ type: 'executor_started', agent: 'executor', status: 'running', run_id: runId })
      sse({
        type: 'executor_completed',
        agent: 'executor',
        status: 'success',
        run_id: runId,
        artifacts: {
          files_read: [{ path: 'app/Policies/AccessPolicy.php', reason: 'Authorize workspace access.' }],
          files_changed: [{
            path: 'app/Policies/AccessPolicy.php',
            change_type: 'modified',
            summary: 'Scope the policy query to the active workspace.',
          }],
          tests_run: [{
            name: 'php artisan test --filter=AccessPolicy',
            status: 'passed',
            summary: 'Access policy checks completed.',
          }],
        },
      })
      sse({ type: 'auditor_started', agent: 'auditor', status: 'running', run_id: runId })
      sse({
        type: 'auditor_completed',
        agent: 'auditor',
        status: 'success',
        run_id: runId,
        artifacts: {
          audit_findings: [{
            id: 'tour-workspace-scope',
            title: 'Workspace scope needs approval',
            severity: 'medium',
            category: 'authorization',
            description: 'The policy change affects workspace access boundaries.',
            status: 'needs_review',
          }],
        },
      })
      setTimeout(() => {
        if (res.destroyed || res.writableEnded) return
        sse({
          type: 'approval_requested',
          agent: 'executor',
          stage: 'executor_approvals',
          status: 'waiting',
          run_id: runId,
          artifacts: { pending_count: 1 },
        })
        res.end()
      }, 8_000)
      return
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
        workflow: 'orchestrator_executor',
        task_type: 'code_edit',
        risk_level: 'medium',
        skill: 'laravel',
        executor_profile: 'backend',
        needs_executor: true,
        memory_mode: 'retrieve',
      },
      artifacts: {
        pipeline_agents: ['orchestrator', 'executor'],
        skipped_agents: ['auditor', 'security-auditor', 'final-reviewer'],
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
      run_id: runId,
      output: isLongPrompt
        ? `Mock stream done for long prompt attachment (${prompt.length} chars)`
        : `Mock stream done for: ${prompt.slice(0, 80)}`,
    })

    if (isLongPrompt) {
      sse({
        type: 'long_prompt_cleaned',
        status: 'success',
        run_id: runId,
        summary: 'Long prompt temporary files were cleaned.',
        artifacts: {
          long_prompt: {
            original_length: prompt.length,
            cleanup_status: 'deleted',
          },
        },
      })
    }

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

  const approvalsMatch = pathname.match(/^\/api\/runs\/([^/]+)\/approvals$/)
  if (approvalsMatch && method === 'GET' && productTourRunIds.has(approvalsMatch[1])) {
    const runId = approvalsMatch[1]
    json(res, {
      stage: 'executor_approvals',
      pending: [{
        id: 'approval-product-tour-access-policy',
        run_id: runId,
        operation_type: 'file_change',
        description: 'Apply the workspace-scoped access policy change.',
        risk_level: 'medium',
        status: 'pending',
        evidence: {
          asking_agent: 'executor',
          why: 'The change affects workspace access boundaries.',
          path: 'app/Policies/AccessPolicy.php',
          before: "return $query->where('active', true);",
          after: "return $query->where('workspace_id', $workspaceId);",
          diff: "- return $query->where('active', true);\n+ return $query->where('workspace_id', $workspaceId);",
        },
      }],
    })
    return
  }

  const streamEventsMatch = pathname.match(/^\/api\/runs\/([^/]+)\/stream-events$/)
  if (streamEventsMatch && method === 'GET') {
    const runId = streamEventsMatch[1]
    const afterSeq = Math.max(0, Number(url.searchParams.get('after_seq') ?? 0))

    if (runId === 'run_e2e_background') {
      if (afterSeq < 1) {
        json(res, {
          run_id: runId,
          status: 'running',
          events: [{ seq: 1, type: 'run_started', run_id: runId, status: 'success' }],
          last_seq: 1,
        })
        return
      }
      json(res, {
        run_id: runId,
        status: 'completed',
        events: [
          {
            seq: 2,
            type: 'run_completed',
            run_id: runId,
            status: 'success',
            output: 'Mock poll completed.',
          },
        ],
        last_seq: 2,
      })
      return
    }

    const events = [
      { seq: 1, type: 'run_started', run_id: runId, status: 'success' },
      { seq: 2, type: 'run_completed', run_id: runId, status: 'success', output: 'Mock poll completed.' },
    ].filter(e => e.seq > afterSeq)
    json(res, {
      run_id: runId,
      status: 'completed',
      events,
      last_seq: events.length > 0 ? events.at(-1)!.seq : afterSeq,
    })
    return
  }

  if (pathname === '/api/project/list' && method === 'GET') {
    json(res, projectListResponse())
    return
  }

  if (pathname === '/api/project/workspace-folders' && method === 'GET') {
    const path = (url.searchParams.get('path') ?? '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '')
    if (path.includes('..')) {
      json(res, {
        available: false,
        message: 'Path traversal is not allowed.',
        workspace: workspaceMeta,
      }, 422)
      return
    }
    json(res, {
      available: true,
      path,
      absolute: path ? `${workspaceMeta.workspace_mount}/${path}` : workspaceMeta.workspace_mount,
      folders: mockWorkspaceFolders[path] ?? [],
      workspace: workspaceMeta,
    })
    return
  }

  if (pathname === '/api/project/register-container-path' && method === 'POST') {
    const body = JSON.parse(await readBody(req) || '{}') as Record<string, unknown>
    const name = String(body.name ?? '').trim() || 'New project'
    const containerPath = String(body.container_path ?? '').trim()
    const activate = body.activate !== false
    const mount = workspaceMeta.workspace_mount
    if (!containerPath.startsWith(mount)) {
      json(res, {
        message: `Container path must be under ${mount}.`,
        workspace: workspaceMeta,
      }, 422)
      return
    }
    const created = !projectsState.some(project => project.container_path === containerPath)
    const hostPath = containerPath.replace(mount, workspaceMeta.workspace_host_prefix)
    const project: MockProject = created
      ? {
          id: `proj_${projectsState.length + 1}`,
          name,
          host_path: hostPath,
          container_path: containerPath,
          is_active: activate,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
      : {
          ...projectsState.find(item => item.container_path === containerPath)!,
          name,
          host_path: hostPath,
          is_active: activate,
          updated_at: new Date().toISOString(),
        }
    projectsState = created
      ? [project, ...projectsState.map(item => ({ ...item, is_active: activate ? false : item.is_active }))]
      : projectsState.map(item => {
        if (item.container_path === containerPath) return project
        return activate ? { ...item, is_active: false } : item
      })
    if (activate) {
      activeProjectId = project.id
    }
    json(res, {
      project,
      created,
      mounted: true,
      available: true,
      error: null,
      manifest_total: 2,
    }, created ? 201 : 200)
    return
  }

  if (pathname === '/api/project' && method === 'GET') {
    json(res, {
      root: '/repo',
      relative: '',
      available: true,
      error: null,
      active_project: activeProject(),
    })
    return
  }

  if (pathname === '/api/project/tree' && method === 'GET') {
    const path = (url.searchParams.get('path') ?? '').replace(/\\/g, '/')
    json(res, {
      path,
      entries: projectTreeState[path] ?? [],
      truncated: false,
    })
    return
  }

  if (pathname === '/api/project/search' && method === 'GET') {
    const q = (url.searchParams.get('q') ?? '').toLowerCase().trim()
    const matches = q
      ? [
          {
            path: 'README.md',
            line: 1,
            preview: 'Mock workspace used by E2E.',
          },
        ].filter(hit => hit.preview.toLowerCase().includes(q) || hit.path.toLowerCase().includes(q))
      : []
    json(res, {
      query: q,
      matches,
    })
    return
  }

  if (pathname === '/api/project/file' && method === 'GET') {
    const path = String(url.searchParams.get('path') ?? '')
    json(res, {
      path,
      contents: projectFilesState[path] ?? '',
    })
    return
  }

  if (pathname === '/api/project/changes' && method === 'GET') {
    json(res, projectChangesState)
    return
  }

  if (pathname === '/api/project/changes' && method === 'POST') {
    const body = JSON.parse(await readBody(req) || '{}') as Record<string, unknown>
    const id = `chg_${projectChangesState.length + 1}`
    const change = {
      id,
      run_id: String(body.run_id ?? 'run_mock'),
      operation_type: 'replace',
      operation_description: `Update ${String(body.path ?? 'file')}`,
      risk_level: 'low',
      status: 'pending',
      evidence: {
        path: String(body.path ?? 'README.md'),
        before: projectFilesState[String(body.path ?? 'README.md')] ?? '',
        after: String(body.new_contents ?? ''),
      },
      created_at: new Date().toISOString(),
    }
    projectChangesState = [change, ...projectChangesState]
    json(res, change)
    return
  }

  if (pathname.startsWith('/api/project/changes/') && pathname.endsWith('/approve') && method === 'POST') {
    const id = pathname.split('/')[4]
    projectChangesState = projectChangesState.map(item =>
      item.id === id ? { ...item, status: 'approved' } : item,
    )
    json(res, { ok: true, id })
    return
  }

  if (pathname.startsWith('/api/project/changes/') && pathname.endsWith('/apply') && method === 'POST') {
    const id = pathname.split('/')[4]
    const change = projectChangesState.find(item => item.id === id)
    if (change?.evidence && typeof change.evidence === 'object') {
      const evidence = change.evidence as { path?: string; after?: string }
      if (evidence.path) {
        projectFilesState[evidence.path] = String(evidence.after ?? '')
      }
    }
    projectChangesState = projectChangesState.map(item =>
      item.id === id ? { ...item, status: 'applied' } : item,
    )
    json(res, { ok: true, id })
    return
  }

  if (pathname.startsWith('/api/project/changes/') && pathname.endsWith('/reject') && method === 'POST') {
    const id = pathname.split('/')[4]
    projectChangesState = projectChangesState.map(item =>
      item.id === id ? { ...item, status: 'rejected' } : item,
    )
    json(res, { ok: true, id })
    return
  }

  if (pathname === '/api/project/register' && method === 'POST') {
    const body = JSON.parse(await readBody(req) || '{}') as Record<string, unknown>
    const name = String(body.name ?? '').trim() || 'New project'
    const hostPath = String(body.host_path ?? '').trim()
    const created = !projectsState.some(project => project.host_path === hostPath)
    const project: MockProject = created
      ? {
          id: `proj_${projectsState.length + 1}`,
          name,
          host_path: hostPath,
          container_path: '/repo',
          is_active: false,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
      : {
          ...projectsState.find(project => project.host_path === hostPath)!,
          name,
          updated_at: new Date().toISOString(),
        }
    projectsState = created
      ? [project, ...projectsState]
      : projectsState.map(item => item.host_path === hostPath ? project : item)
    json(res, {
      project,
      created,
      mounted: projectUnderWorkspace(hostPath),
      under_workspace: projectUnderWorkspace(hostPath),
      message: 'Mock register ok',
    })
    return
  }

  if (pathname.startsWith('/api/project/') && pathname.endsWith('/activate') && method === 'POST') {
    const id = pathname.split('/')[3]
    activeProjectId = id
    projectsState = projectsState.map(project => ({
      ...project,
      is_active: project.id === id,
      updated_at: new Date().toISOString(),
    }))
    const project = projectsState.find(item => item.id === id) ?? projectsState[0]
    json(res, {
      project,
      repo_root: '/repo',
      available: true,
      error: null,
      manifest_total: 2,
    })
    return
  }

  if (pathname.startsWith('/api/project/') && method === 'DELETE') {
    const id = pathname.split('/')[3]
    projectsState = projectsState.filter(project => project.id !== id)
    if (activeProjectId === id) {
      activeProjectId = projectsState.find(project => project.is_active)?.id ?? projectsState[0]?.id ?? null
    }
    json(res, { ok: true })
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

  if (pathname === '/api/knowledge/recent' && method === 'GET') {
    json(res, knowledgeRecent)
    return
  }

  if (pathname === '/api/knowledge/urls' && method === 'POST') {
    knowledgeRecent = {
      data: [
        {
          id: 'k_url_2',
          human_summary: 'Mock knowledge article',
          content: 'Mock knowledge article body stored from an imported URL.',
          type: 'knowledge',
          source: 'url',
          tags: ['knowledge', 'research', 'ai'],
          is_active: true,
          updated_at: new Date().toISOString(),
        },
        ...knowledgeRecent.data,
      ],
    }
    json(res, {
      created: 1,
      skipped: 1,
      failed: 0,
      items: [
        { source: 'https://example.com/article', status: 'imported', title: 'Mock knowledge article', memory_id: 'k_url_2' },
        { source: 'https://youtu.be/abc123XYZ09', status: 'skipped', message: 'Duplicate content.' },
      ],
    })
    return
  }

  if (pathname === '/api/knowledge/import-memory' && method === 'POST') {
    const raw = await readBody(req)
    let source = 'codex'
    try {
      source = String(JSON.parse(raw || '{}').source ?? 'codex')
    }
    catch {
      //
    }
    const title = source === 'claude' ? 'Imported Claude memory' : 'Imported Codex memory'
    knowledgeRecent = {
      data: [
        {
          id: `k_${source}_1`,
          human_summary: title,
          content: `${title} from local fixture memory.`,
          type: 'knowledge',
          source,
          tags: ['knowledge', source],
          is_active: true,
          updated_at: new Date().toISOString(),
        },
        ...knowledgeRecent.data,
      ],
    }
    json(res, {
      created: 1,
      skipped: 0,
      failed: 0,
      items: [{ source, status: 'imported', title, memory_id: `k_${source}_1` }],
    })
    return
  }

  if (pathname === '/api/settings/inference-catalog' && method === 'GET') {
    json(res, {
      version: '2026-06-19',
      cloud_only: true,
      providers: [
        {
          provider: 'ollama-cloud',
          name: 'Ollama Cloud',
          auth: 'api_key',
          configured: true,
          disabled: false,
          all_cloud_models: [{ id: 'kimi-k2.6:cloud', label: 'Kimi K2.6 (Cloud)' }],
          recommended_models: [{ id: 'kimi-k2.6:cloud', label: 'Kimi K2.6 (Cloud)', auto_selected: true }],
        },
        {
          provider: 'anthropic',
          name: 'Anthropic',
          auth: 'api_key',
          configured: true,
          disabled: false,
          all_cloud_models: [{ id: 'claude-opus-4-8', label: 'Claude Opus 4.8' }],
          recommended_models: [{ id: 'claude-opus-4-8', label: 'Claude Opus 4.8', auto_selected: true }],
        },
        {
          provider: 'codex',
          name: 'Codex (ChatGPT)',
          auth: 'oauth',
          configured: true,
          disabled: false,
          all_cloud_models: [{ id: 'gpt-5.5', label: 'GPT-5.5 (Codex)' }],
          recommended_models: [{ id: 'gpt-5.5', label: 'GPT-5.5 (Codex)', auto_selected: true }],
        },
      ],
      ollama: [
        { id: 'kimi-k2.6:cloud', label: 'Kimi K2.6 (Cloud)' },
        { id: 'glm-5.2:cloud', label: 'GLM 5.2 (Cloud)' },
      ],
      anthropic: [
        { id: 'claude-opus-4-8', label: 'Claude Opus 4.8' },
        { id: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6' },
      ],
      codex: [
        { id: 'gpt-5.5', label: 'GPT-5.5 (Codex)' },
      ],
      anthropic_configured: true,
      codex_connected: true,
    })
    return
  }

  if (pathname === '/api/settings/model-recommendations' && method === 'GET') {
    const role = url.searchParams.get('role') ?? 'orchestrator'
    const provider = url.searchParams.get('provider') ?? 'ollama-cloud'
    json(res, {
      role,
      provider,
      recommended_models: [
        { id: 'kimi-k2.6:cloud', label: 'Kimi K2.6 (Cloud)', score: 72, auto_selected: true },
      ],
      auto_selected: 'kimi-k2.6:cloud',
    })
    return
  }

  if (pathname === '/api/settings/model-recommendations/apply' && method === 'POST') {
    json(res, {
      applied: {
        orchestrator: { provider: 'ollama-cloud', model: 'kimi-k2.6:cloud', score: 72 },
        executor: { provider: 'moonshot', model: 'kimi-k2.7-code', score: 88 },
      },
    })
    return
  }

  if (pathname === '/api/providers/presets' && method === 'GET') {
    json(res, [
      { slug: 'deepseek', name: 'DeepSeek', type: 'openai_compatible', configured: false },
      { slug: 'moonshot', name: 'Kimi (Moonshot)', type: 'openai_compatible', configured: false },
    ])
    return
  }

  if (pathname === '/api/oauth/codex/status' && method === 'GET') {
    json(res, { connected: true, configured: true, expires_at: null, account_hint: 'mock@example.com', last_refresh: null })
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
    json(res, {
      data: [
        {
          id: 'usage_1',
          model: 'llama3',
          provider: 'ollama',
          input_tokens: 1200,
          output_tokens: 340,
          cost_usd: 0.0025,
          created_at: '2026-05-26T10:00:00.000000Z',
        },
      ],
      total: 1,
      current_page: 1,
    })
    return
  }

  if (pathname === '/api/usage/summary' && method === 'GET') {
    json(res, {
      total_input_tokens: 1200,
      total_output_tokens: 340,
      total_cost_usd: 0.0025,
      breakdown: [
        { provider: 'ollama', model: 'llama3', input_tokens: 1200, output_tokens: 340, cost_usd: 0.0025, call_count: 1 },
      ],
    })
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
    json(res, {
      data: [
        {
          id: 'fb_1',
          target_type: 'run',
          target_id: 'r_1',
          signal: 'positive',
          comment: 'Helpful answer',
          processed: false,
          created_at: '2026-05-26T10:00:00.000000Z',
        },
      ],
      total: 1,
      current_page: 1,
    })
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
