# BosskuAI VPS Docker Deployment Playbook

Senior-level reference for deploying Laravel + Nuxt + database + Redis on a single VPS using Docker Compose, behind Caddy or Nginx, with backups and rollback. Each section pairs the wrong-way pattern with the right-way fix and the verification step that proves it.

## Audit flow

1. Read `docker-compose.yml`, every `Dockerfile`, `.env.example`, reverse-proxy config (`Caddyfile` or `nginx.conf`), `cron`/systemd timers, and the deploy script.
2. SSH to the VPS and check: firewall (`ufw status verbose`), running containers (`docker ps`), open ports (`ss -tulpn`), disk (`df -h`, `du -sh /var/lib/docker`), TLS expiry, backup files on disk and in remote storage.
3. Read the last 100 lines of every container's logs.
4. Trace one critical path: external request → DNS → reverse proxy → SSL → app container → DB/Redis container → response.
5. Verify with a real deploy → smoke test → forced rollback → restore-from-backup drill.

## Best-practice checks (one-liner version)

- VPS firewall blocks everything except 22 (SSH, key-only), 80, 443.
- Database and Redis ports are **not** published to the host — only on the internal Docker network.
- Every image runs as a non-root user.
- `depends_on` uses `condition: service_healthy`, not just service order.
- Reverse proxy terminates SSL with auto-renewal (Caddy or Certbot timer).
- Volumes for data (DB, uploads) are named volumes, not bind mounts that disappear on `docker compose down -v`.
- Backups run on a timer, are tested by being restored to a clean instance at least monthly.
- Deploy script runs `php artisan migrate --force`, `queue:restart`, and a smoke check before declaring success.
- Rollback path is documented and exercised, not theoretical.
- Log rotation configured at the daemon level (`/etc/docker/daemon.json`), not just inside the app.

## Recommended commands

```bash
ufw status verbose
ss -tulpn | grep -E ':(80|443|3306|5432|6379)'
docker compose ps
docker compose logs --tail 100 -f
docker compose exec app php artisan about
docker compose exec db pg_isready -U app          # or mysqladmin ping
df -h /var/lib/docker
docker system df
```

---

## Worked anti-patterns and fixes

### 1. Database port published to host

**Wrong**

```yaml
db:
  image: postgres:16
  ports:
    - "5432:5432"            # exposed to the public internet
  environment:
    POSTGRES_PASSWORD: ${DB_PASSWORD}
```

If the VPS firewall is misconfigured (one `ufw allow` away), your DB is on the public internet with whatever password.

**Right** — only expose what the reverse proxy needs. The DB stays on the internal Docker network:

```yaml
services:
  db:
    image: postgres:16
    expose:                   # internal only; no host binding
      - "5432"
    networks: [internal]
    environment:
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/postgresql/data
    restart: unless-stopped

  app:
    build: .
    networks: [internal, web]
    environment:
      DB_HOST: db             # DNS resolves inside Docker network

  proxy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    networks: [web]

networks:
  internal:
  web:

volumes:
  db_data:
```

**Verify** — from outside the VPS: `nmap -p 3306,5432,6379 your-vps-ip` should show `closed` or `filtered`. From inside: `docker compose exec app pg_isready -h db` works.

### 2. Container running as root

**Wrong**

```dockerfile
FROM php:8.3-fpm
COPY . /var/www/html
CMD ["php-fpm"]
```

The PHP process runs as root. A code-execution vulnerability becomes a container-root vulnerability.

**Right**

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache fcgi && \
    addgroup -g 1000 app && adduser -G app -u 1000 -D app

WORKDIR /var/www/html
COPY --chown=app:app . .

USER app
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000

CMD ["php-fpm"]
```

For Node/Nuxt, base off `node:20-alpine` and `USER node` (already exists in the official image).

**Verify** — `docker compose exec app id` shows non-zero UID. Static scan with `docker scout` or `trivy image your-app:latest`.

### 3. `depends_on` without health checks

**Wrong**

```yaml
app:
  depends_on:
    - db
    - redis
```

Compose starts `db` then `app`, but the DB container is "up" before Postgres is actually accepting connections. The app crashes on first connect, restarts, sometimes succeeds — flakiness.

**Right**

```yaml
db:
  image: postgres:16
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U $$POSTGRES_USER"]
    interval: 5s
    timeout: 5s
    retries: 10

redis:
  image: redis:7-alpine
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 3s
    retries: 10

app:
  depends_on:
    db:    { condition: service_healthy }
    redis: { condition: service_healthy }
```

**Verify** — `docker compose down && docker compose up -d`. The app container should not log connection errors during startup. Repeat 5 times.

### 4. SSL: forgetting renewal

**Wrong** — Certbot run once at install time, never again. 90 days later, certificate expires, site goes down on a Saturday.

**Right A — Caddy auto-renews silently:**

```Caddyfile
{
    email ops@example.com
}

example.com {
    reverse_proxy app:80
    encode zstd gzip
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "strict-origin-when-cross-origin"
    }
    log {
        output file /var/log/caddy/access.log
    }
}
```

**Right B — Nginx + Certbot timer:**

Run Certbot in a separate container with a systemd timer or a cron entry:

```cron
0 3 * * * docker compose run --rm certbot renew --quiet && docker compose exec proxy nginx -s reload
```

**Verify** — `curl -vI https://example.com 2>&1 | grep -i 'expire\|notAfter'`. Force-renew once in staging to confirm the reload step works (not just the renewal).

### 5. Backups that aren't restored

**Wrong**

```bash
0 2 * * * docker compose exec -T db pg_dump -U app app > /backups/db.sql
```

The cron runs. The file gets bigger. Nobody's ever tried to restore it. When a real incident hits, the restore fails because of a version mismatch or the dump is truncated.

**Right** — backup + offsite + restore drill on a schedule:

```bash
#!/bin/bash
# /usr/local/bin/backup-db.sh
set -euo pipefail

ts=$(date -u +%Y%m%dT%H%M%SZ)
file="/backups/db-${ts}.sql.gz"

docker compose exec -T db pg_dump -U app -F c app | gzip > "$file"

# Offsite (S3, Cloudflare R2, Backblaze B2 — pick one with versioning)
aws s3 cp "$file" "s3://acme-backups/db/${ts}.sql.gz" --storage-class STANDARD_IA

# Retention: keep 7 daily, 4 weekly, 12 monthly — local
find /backups -name 'db-*.sql.gz' -mtime +7 -delete

# Health check (Healthchecks.io / Better Stack / Dead Man's Snitch)
curl -fsS --retry 3 https://hc-ping.com/abcd1234 > /dev/null
```

**Monthly restore drill:**

```bash
# /usr/local/bin/restore-test.sh
docker run --rm -v db_test:/var/lib/postgresql/data postgres:16 ...
gunzip -c /backups/db-*.sql.gz | head -1   # verify gzip integrity
docker compose -f compose.test.yml up -d db-test
docker compose -f compose.test.yml exec -T db-test pg_restore -U app -d app < latest-dump
docker compose -f compose.test.yml exec db-test psql -c 'SELECT count(*) FROM users'
```

**Verify** — set a calendar event. The drill must succeed. If it doesn't, the backup is fiction.

### 6. Volume mount that loses data on `down -v`

**Wrong**

```yaml
db:
  volumes:
    - ./pgdata:/var/lib/postgresql/data    # bind mount; ./pgdata might be in a tmp dir
```

Or worse, the developer runs `docker compose down -v` (which removes named volumes too) thinking it's harmless.

**Right** — named volume + explicit production protection:

```yaml
volumes:
  db_data:
    driver: local
    name: appname_db_data           # explicit name; survives compose project rename
```

Plus a one-line comment in the deploy script:

```bash
# Deploy script — never use -v in production
docker compose down       # NOT: docker compose down -v
```

**Verify** — `docker volume ls`. Backups must include named volume contents, not just bind mounts. Try `docker compose down && docker compose up -d` in staging — data must persist.

### 7. Reverse proxy 502 on first deploy

**Wrong** — the proxy starts before the app is ready. Users hit a fresh deploy and see 502s for 10–30 seconds.

**Right** — proxy depends on app health, plus the proxy retries:

```yaml
app:
  healthcheck:
    test: ["CMD", "curl", "-fsS", "http://localhost/health"]
    interval: 5s
    timeout: 5s
    retries: 12
    start_period: 30s

proxy:
  depends_on:
    app: { condition: service_healthy }
```

Also configure the proxy to retry transient upstream failures:

```Caddyfile
example.com {
    reverse_proxy app:80 {
        lb_policy random
        lb_try_duration 30s
        lb_try_interval 1s
    }
}
```

For zero-downtime deploys, run two app containers behind the proxy and roll one at a time, or use Docker Swarm / Kubernetes (out of scope here).

**Verify** — during a deploy, run `while true; do curl -sI https://example.com | head -1; sleep 1; done`. There should be no `502` lines.

### 8. Disk filling from logs

**Wrong** — default Docker logging driver writes JSON without rotation. Containers run for months. `/var/lib/docker/containers/*/*.log` reaches 50 GB. Everything stops.

**Right** — global log rotation:

```json
// /etc/docker/daemon.json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "50m",
    "max-file": "5"
  }
}
```

Restart the daemon: `systemctl restart docker`. Add a disk-usage alert (Healthchecks.io, Netdata, or a `df -h` cron).

**Verify** — `docker info | grep -i 'logging driver'`. Run a high-log-volume container and confirm the file count doesn't climb forever.

### 9. Missing `queue:restart` after deploy

**Wrong** — deploy script runs `git pull && docker compose up -d --build`. Workers keep running the old code until they exit naturally. Customers see inconsistent behavior for hours.

**Right** — explicit restart hook:

```bash
#!/bin/bash
# deploy.sh
set -euo pipefail

cd /opt/app
git pull --ff-only
docker compose pull
docker compose build --pull
docker compose up -d --remove-orphans

# Wait for app health
for i in {1..30}; do
  if curl -fsS http://localhost/health > /dev/null; then break; fi
  sleep 2
done

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan queue:restart      # graceful worker restart
docker compose exec -T app php artisan horizon:terminate  # if using Horizon

# Smoke check
curl -fsS https://example.com/api/healthz
```

**Verify** — deploy a change to a queued job. Within `max-time`, confirm a fresh worker is processing the new code.

### 10. Rollback that doesn't exist

**Wrong** — "We tag releases" but the deploy is just `git pull`. To roll back you'd `git revert`, rebuild, and pray.

**Right** — image tags are the deploy unit, with explicit rollback:

```bash
# Build pipeline tags every commit
docker build -t registry.example.com/app:${GIT_SHA} .
docker push    registry.example.com/app:${GIT_SHA}

# deploy.sh accepts a tag
TAG=${1:-latest}
sed -i "s|app:.*|app:${TAG}|" docker-compose.yml
docker compose up -d --remove-orphans

# rollback.sh
TAG=${1:?usage: rollback.sh <previous-sha>}
./deploy.sh "$TAG"
```

The previous-N tags must remain available. Either keep them in the registry indefinitely or pre-pull on the VPS:

```bash
docker pull registry.example.com/app:${PREVIOUS_TAG}    # ready for instant rollback
```

**Verify** — once a quarter, run a rollback drill in staging. Time it. If it takes more than 5 minutes, the runbook needs work.

---

## Production audit matrix

| Layer       | Check                                                | Command / artifact                          |
|-------------|------------------------------------------------------|---------------------------------------------|
| Firewall    | Only 22, 80, 443 open externally                     | `ufw status verbose`                        |
| Ports       | DB/Redis not bound to host                           | `ss -tulpn`                                 |
| Image       | Non-root user, healthcheck present                   | Dockerfile review + `docker compose ps`     |
| Compose     | `depends_on` uses `service_healthy`                  | `docker-compose.yml` review                 |
| SSL         | Auto-renewal verified by force-renew                 | `curl -vI` + Caddy/Certbot logs             |
| Volumes     | Data on named volumes, not bind mounts               | `docker volume ls`                          |
| Backup      | Daily backup + offsite + monthly restore drill       | backup script + drill log                   |
| Logs        | Daemon-level rotation                                | `/etc/docker/daemon.json`                   |
| Deploy      | Health check + `queue:restart` + smoke check         | deploy script                               |
| Rollback    | Per-commit image tags + tested rollback              | rollback script + drill                     |
| Secrets     | `.env` is 600, never committed                       | `ls -l .env`, `git log -- .env`             |
| Disk        | Alert below 20% free                                 | monitoring                                  |

## Output expectation

When auditing, return:

1. **Findings table** — file:line / config key, severity, evidence, fix.
2. **Smallest fix sequence** — minimum P0/P1 set to ship.
3. **Verification** — exact command, response, or drill log that proves each fix.
4. **De-scope** — what is intentionally not touched yet, and why.

## Further reading

- `bosskuai-docker-playbook.md` — Compose patterns and image hygiene shared across environments.
- `bosskuai-redis-caching-queues-playbook.md` — eviction policy and persistence settings.
- `bosskuai-cybersecurity-risk-playbook.md` — STRIDE/OWASP baseline that complements the firewall and image hardening checks.
