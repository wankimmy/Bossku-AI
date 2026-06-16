/**
 * BosskuAI Kernel SDK (TypeScript) — a thin client over the kernel platform API.
 *
 * Covers assistants, threads, cron jobs, webhooks, graph introspection, and the
 * time-travel checkpoint endpoints. Pair with the SSE stream
 * (GET /api/runs/{id}/stream-events) to drive a Studio view.
 *
 *   const sdk = new BosskuKernel("https://host/api", "API_TOKEN");
 *   const graph = await sdk.getGraph("default_pipeline");
 *   const cps   = await sdk.listCheckpoints(runId);
 *   const fork  = await sdk.fork(runId, cps[0].id, { plan: "revised" });
 */

export interface GraphTopology {
  name: string;
  entry: string;
  nodes: string[];
  edges: { from: string; to: string }[];
  branches: { from: string; routes: Record<string, string> }[];
}

export interface CheckpointSummary {
  id: string;
  parent_id: string | null;
  step: number;
  source: "input" | "loop" | "interrupt" | "fork";
  next: (string | { node: string; input: Record<string, unknown> })[];
}

export interface Assistant {
  id: string;
  name: string;
  slug: string;
  graph: string;
  config: Record<string, unknown> | null;
  enabled: boolean;
}

export type WebhookEvent =
  | "run.completed"
  | "run.interrupted"
  | "run.failed"
  | "checkpoint.created";

export class BosskuKernel {
  constructor(
    private readonly baseUrl: string,
    private readonly token?: string,
  ) {}

  private async request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const res = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers: {
        "Content-Type": "application/json",
        ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!res.ok) {
      throw new Error(`BosskuKernel ${method} ${path} failed: ${res.status}`);
    }
    return (await res.json()) as T;
  }

  // ── Graphs (Studio) ──────────────────────────────────────────────
  listGraphs(): Promise<{ graphs: string[] }> {
    return this.request("GET", "/graphs");
  }
  getGraph(name: string): Promise<GraphTopology> {
    return this.request("GET", `/graphs/${encodeURIComponent(name)}`);
  }

  // ── Assistants ───────────────────────────────────────────────────
  listAssistants(): Promise<{ data: Assistant[] }> {
    return this.request("GET", "/assistants");
  }
  createAssistant(input: Partial<Assistant>): Promise<Assistant> {
    return this.request("POST", "/assistants", input);
  }
  deleteAssistant(id: string): Promise<{ message: string }> {
    return this.request("DELETE", `/assistants/${id}`);
  }

  // ── Threads ──────────────────────────────────────────────────────
  createThread(input: { assistant_id?: string; title?: string }): Promise<{ id: string }> {
    return this.request("POST", "/threads", input);
  }
  getThread(id: string): Promise<unknown> {
    return this.request("GET", `/threads/${id}`);
  }

  // ── Cron jobs ────────────────────────────────────────────────────
  createCron(input: {
    assistant_id: string;
    name: string;
    cron_expression: string;
    prompt?: string;
  }): Promise<unknown> {
    return this.request("POST", "/cron-jobs", input);
  }

  // ── Webhooks ─────────────────────────────────────────────────────
  createWebhook(input: { url: string; events: WebhookEvent[]; secret?: string }): Promise<unknown> {
    return this.request("POST", "/webhooks", input);
  }

  // ── Time-travel ──────────────────────────────────────────────────
  listCheckpoints(runId: string): Promise<{ run_id: string; checkpoints: CheckpointSummary[] }> {
    return this.request("GET", `/runs/${runId}/checkpoints`);
  }
  fork(
    runId: string,
    checkpointId: string,
    statePatch: Record<string, unknown> = {},
  ): Promise<{ forked_run_id: string }> {
    return this.request("POST", `/runs/${runId}/fork`, {
      checkpoint_id: checkpointId,
      state_patch: statePatch,
    });
  }
}
