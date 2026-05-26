# Verify file reads and project commands (any registered repo)

Bossku runs agents against whichever project is **active** in **Project → Paths**. There is no splitlah-only path in the orchestrator — only `ProjectPathResolver::repoRoot()` for the active registration.

## Prerequisites

1. Host folder is under `BOSSKU_WORKSPACE_HOST_PREFIX` (see `Bossku-AI/docker-compose.yml` workspace mount).
2. Register the repo in Bossku UI (name + host path), then **Activate**.
3. Bossku backend rebuilt if using Docker Compose from agents: `docker compose build backend && docker compose up -d` (needs `docker.sock` on backend).
4. The target repo has its own `docker-compose.yml` / `compose.yaml` if you want `docker compose` commands; service names come from **that** file (e.g. `app`, `web`, `api`).

## Register any public/user repo

Example host paths (Windows):

- `C:\Users\...\Documents\Safwan\splitlah` → container `/workspace/splitlah`
- `C:\Users\...\Documents\Safwan\my-other-app` → container `/workspace/my-other-app`

After activate, the API returns `runtime_hints` (framework, compose services, suggested `docker compose exec <service>` examples).

## File-read / security audit

1. Activate the project you want to audit.
2. Run a security audit prompt in BosskuAI.
3. Confirm `preflight_reads_done` in the activity feed.
4. Final result should not claim "content retrieval failed" when files exist and were read in preflight.

## Project commands (from Bossku backend)

Replace `YOUR_PROJECT` with the active project's container folder name (basename of `container_path`):

```bash
docker compose exec backend sh -c "cd /workspace/YOUR_PROJECT && docker compose config"
docker compose exec backend sh -c "cd /workspace/YOUR_PROJECT && docker compose up -d"
```

Use the **service name from that repo's compose file** (see `runtime_hints.suggested_compose_service` after activate), for example:

```bash
docker compose exec backend sh -c "cd /workspace/YOUR_PROJECT && docker compose exec app php artisan test"
```

For Laravel without compose, agents may use `php artisan test` at the repo root (cwd = active project).

## Approval flow

When `BOSSKU_REQUIRE_USER_APPROVAL=true`, each file change is shown in the **Change approval** modal before it is written. Allowlisted `commands_run` entries (e.g. `php artisan test`, `git status`) run automatically unless you set `BOSSKU_REQUIRE_USER_APPROVAL_FOR_COMMANDS=true`.

## PHPUnit (Bossku-AI)

```bash
docker compose exec backend php artisan test --filter=ProjectRuntimeHints|SecurityAuditorPreflight|ProjectCommandRunner|ExecutorApproval
```
