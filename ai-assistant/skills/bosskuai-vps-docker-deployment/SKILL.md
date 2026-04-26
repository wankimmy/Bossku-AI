---
name: bosskuai-vps-docker-deployment
description: Use this for VPS Docker deployment, production Docker Compose, Nginx reverse proxy, SSL, backups, zero/low-downtime deploys, observability, firewalling, and rollback planning.
---

# BosskuAI VPS Docker Deployment

Use this skill when deploying a Laravel, Nuxt, Node, or multi-service app to a VPS using Docker.

## Deployment baseline

- One documented `docker compose` entrypoint per environment.
- No secrets committed in compose files, images, memory, or docs.
- Reverse proxy terminates TLS and forwards only required services.
- Containers run as non-root where practical and expose minimal ports.
- Volumes are explicit; backups cover DB, uploads, and env/config needed to restore.
- Health checks, restart policy, log rotation, and rollback path are defined.

## Checklist

- Server: SSH keys only, firewall enabled, fail2ban or equivalent, non-root deploy user.
- Docker: pinned images, `.env` usage, health checks, networks, volumes, resource limits where needed.
- App: production env, cache/config build steps, migrations handled safely, queue workers supervised.
- Nginx/Caddy/Traefik: TLS renewal, gzip/brotli where appropriate, upload limits, WebSocket support if needed.
- Database: persistent volume, scheduled backup, restore test, restricted external access.
- Monitoring: uptime check, disk alerts, container restart alerts, app error logs.

## Guardrails

- Do not expose database, Redis, or internal services publicly.
- Do not run `composer install` or `npm install` on every request path.
- Do not deploy without backup/rollback for database-affecting releases.
- Do not use `latest` tags in production unless intentionally controlled.

## Output format

```text
Target deployment: [stack + VPS assumptions]
Production blockers: [P0 items]
Recommended compose topology: [services]
Deploy steps: [commands]
Rollback: [how to revert]
Verification: [curl/health/logs/backup restore]
```

## References

- `../../references/playbooks/bosskuai-vps-docker-deployment-playbook.md`
- `../../references/checklists/vps-docker-deployment-checklist.md`
- `../../references/checklists/expert-cofounder-stack-checklist.md`
