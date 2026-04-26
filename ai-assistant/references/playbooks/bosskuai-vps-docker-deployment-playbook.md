# BosskuAI VPS Docker Deployment Playbook

## Production compose topology

Typical services:

- reverse-proxy: Caddy/Nginx/Traefik
- app: Laravel/PHP-FPM or Node/Nuxt server
- worker: queue worker
- scheduler: cron/scheduler container
- db: MariaDB/MySQL/PostgreSQL with persistent volume
- redis: internal cache/queue service

## Deployment workflow

1. Create non-root deploy user and SSH key-only access.
2. Configure firewall: expose only 22, 80, 443.
3. Place `.env` on server only; never commit secrets.
4. Pull pinned image or git tag.
5. Run build/migrate steps intentionally.
6. Start services with health checks.
7. Verify routes, logs, queues, SSL renewal, and backups.

## Commands to request/check

```bash
docker compose config
docker compose ps
docker compose logs --tail=100 app
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
curl -I https://example.com
```

## Rollback rule

A release is not production-ready unless the app image, env/config, database migration strategy, and uploaded file state can be restored or safely rolled forward.

## Production hardening matrix

### Compose design

- Separate app, worker, scheduler, database, Redis, and reverse proxy services.
- Use internal Docker networks; expose only reverse proxy ports externally.
- Use named volumes for database and uploaded files.
- Pin images by version or digest; avoid `latest` in production.
- Add health checks for app, DB, Redis, and proxy.
- Configure restart policies and log rotation.

### Laravel + Nuxt deployment notes

- Laravel build: install Composer dependencies without dev packages, cache config/routes/views, run migrations intentionally, restart queue workers.
- Nuxt build: build artifact once, run Nitro server or static output according to deployment mode, set runtime config via env.
- Queue worker: deploy as a separate container/process; run `php artisan queue:restart` after release.
- Scheduler: use a dedicated scheduler container or host cron that calls `schedule:run`.

### VPS security baseline

```bash
adduser deploy
usermod -aG docker deploy
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

- Use SSH keys only; disable password login where possible.
- Keep DB and Redis private to Docker network.
- Never commit `.env`, database dumps, private keys, or backup archives.
- Use fail2ban or provider-level SSH rate limiting.

### Backup and rollback contract

A production deployment is incomplete until these are defined:

- database backup schedule,
- upload/storage backup schedule,
- restore test command,
- previous image/tag rollback,
- migration rollback or safe forward-only plan,
- DNS/TLS renewal check,
- disk-space alert.

### Verification commands

```bash
docker compose config
docker compose pull
docker compose up -d --remove-orphans
docker compose ps
docker compose logs --tail=100 app
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
curl -fsS https://example.com/health
```
