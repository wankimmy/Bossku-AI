---
name: bosskuai-vps-docker-deployment
description: Use this for VPS Docker deployment on any provider — server hardening, production Docker Compose topology, Caddy/Traefik/nginx reverse proxy with TLS, registry-based deploys, zero or low-downtime rollouts, migrations, off-box backups and restore drills, monitoring, firewalling, and rollback planning for Laravel, Nuxt, Node, and Go apps.
---

# BosskuAI VPS Docker Deployment

Use this skill when deploying a Laravel, Nuxt, Node, Go, or multi-service app to a single VPS (or a small pair) with Docker, on Hostinger, Hetzner, DigitalOcean, Linode, or a bare cloud VM.

## How this differs from nearby skills

- **`bosskuai-docker`**: the Dockerfile and Compose files themselves; this skill puts them on a server and keeps them running.
- **`bosskuai-hostinger-hosting`**: Hostinger panel, plans, non-Docker layouts, and compromise recovery; use both when the VPS is Hostinger.
- **`bosskuai-aws-deployment`**: move there when you need managed databases, autoscaling, or compliance boundaries.
- **`bosskuai-devops-iac`**: pipeline and infra principles; this skill is the single-box implementation.
- **`bosskuai-ci-cd-pipelines`**: builds and pushes the image; this skill pulls and runs it.

## Mindset

- One box means one blast radius: backups off the box, secrets off the image, rollback by tag.
- Build on CI, run on the server; never build production images on the VPS.
- Everything a restore needs (env, compose, volumes, DNS) is written down and tested.
- The proxy owns TLS and exposure; app containers publish nothing to the host.

## Provisioning baseline

1. Ubuntu LTS; `deploy` user with sudo; SSH keys only; `PermitRootLogin no`, `PasswordAuthentication no`.
2. UFW (SSH, 80, 443) and fail2ban; `unattended-upgrades`; swap sized for the plan; `vm.overcommit_memory=1` if Redis runs here.
3. Docker CE from the official repository; `deploy` in the `docker` group (this is root-equivalent; treat the user accordingly); daemon `log-driver: json-file` with `max-size`/`max-file`.
4. A private registry token (GHCR, ECR, Docker Hub) for pulls; no build tools on the box.

## Compose topology

- One compose project per environment (`/srv/<app>/<env>/`): `compose.yml` + `.env` (`600`) + `overrides` per env.
- Proxy: Caddy (automatic TLS, simplest), Traefik (labels, multi-app), or nginx + certbot. Only the proxy publishes 80/443.
- Services: `app` (php-fpm+nginx image, FrankenPHP/Octane, Node, or Go), `worker` (queue), `scheduler` (`schedule:run` loop or cron container), `db` (named volume, internal network only), `redis`, optional `meilisearch`/`minio`.
- Networks: `edge` (proxy ↔ app) and `internal` (app ↔ db/redis); db and redis never on `edge`.
- Health checks on every service; `restart: unless-stopped`; memory limits on app and db; pinned image tags.

## Deploy flow

1. CI builds the image, tags `sha-<short>` and `env-latest`, pushes to the registry.
2. Server: `docker compose pull app worker scheduler` then run migrations once: `docker compose run --rm app php artisan migrate --force` (gated; migrations backward compatible with the running version).
3. Roll: `docker compose up -d --wait --no-deps app worker scheduler`; the proxy routes to healthy containers only.
4. Zero-downtime: two `app` replicas behind the proxy with `start_first` rollout, or blue/green compose projects on alternate ports swapped in the proxy config.
5. Post-deploy: health URL, one authenticated request, queue processes a job, logs clean for 5 minutes.
6. Rollback: `IMAGE_TAG=<previous> docker compose up -d --wait app worker scheduler`; keep the last five tags in the registry.

## Stack notes

- **Laravel**: `config:cache`, `route:cache`, `view:cache` at image build; `storage/` and uploads on a volume; `queue:work --tries=3 --max-time=3600` under compose restart; scheduler as `while true; do php artisan schedule:run; sleep 60; done` or supercronic; Reverb/Horizon as separate services.
- **Nuxt / Node**: run the Nitro/Node server as a non-root user; `NITRO_PORT`, `NUXT_*` runtime config from env; no `pnpm install` at runtime.
- **Go**: static binary on a scratch or distroless image; `GOMEMLIMIT` set to the container limit.
- **Database**: `POSTGRES_*`/`MYSQL_*` from `.env`; tuned `shared_buffers`/`innodb_buffer_pool_size` to the plan; never publish the port.

## Backups and restore

- Nightly `pg_dump -Fc` or `mysqldump --single-transaction` from a sidecar, plus uploads volume, shipped off-box with `restic` or `rclone` to S3-compatible/B2 storage; encrypted; 30-day retention.
- Provider snapshots are for quick rollback, not backups.
- Quarterly restore drill into a scratch VPS: time it, record it in the runbook.

## Monitoring

- External uptime check on the health URL; disk, memory, and container-restart alerts (node exporter + Grafana Cloud, or Uptime Kuma + a cron disk check).
- Centralized logs optional (Loki/Vector) once more than one box exists; until then `docker compose logs` with rotation.
- Certificate expiry alert even with auto-renewal.

## Verification

```bash
docker compose config --quiet && docker compose ps
docker compose logs --tail=100 app worker
curl -fsS https://<domain>/healthz
ufw status verbose && sshd -T | grep -Ei 'permitrootlogin|passwordauthentication'
restic snapshots | tail -3        # or the equivalent for your backup tool
```

## Guardrails

- Do not expose database, Redis, or internal services publicly; do not publish their ports.
- Do not build images or run `composer install`/`npm install` on the production box.
- Do not deploy a database-affecting release without a fresh backup and a rollback tag.
- Do not use `latest` tags in production.
- Do not keep secrets in images, compose files, or git; `.env` on the box only, `600`.

## Output format

```text
Target: [provider, plan, OS] - Stack: [services]
Hardening state: [ssh, firewall, fail2ban, updates, docker logging]
Compose topology: [proxy, app, worker, scheduler, db, redis, networks, volumes]
Deploy steps: [pull → migrate → up --wait → verify]
Zero-downtime approach: [replicas | blue/green | none, why]
Rollback: [previous tag command]
Backups: [what, where off-box, last restore drill]
Monitoring: [uptime, disk, restarts, cert expiry]
Production blockers: [P0 items]
```

## References

- `../../references/playbooks/bosskuai-vps-docker-deployment-playbook.md`
- `../../references/checklists/vps-docker-deployment-checklist.md`
- `../../references/checklists/docker-checklist.md`
- `../../references/checklists/hostinger-hosting-checklist.md`
