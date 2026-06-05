# Production deployment (Bossku-AI)

Bossku defaults to **easy local OSS setup** (open API, workspace mount, docker.sock). Use this guide when exposing an instance to the internet.

## Compose

```bash
cd Bossku-AI
cp app/.env.example app/.env
# Set strong DB_PASSWORD, BOSSKU_API_TOKEN, APP_KEY, OLLAMA_API_KEY, etc.

docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

The [docker-compose.prod.yml](../docker-compose.prod.yml) overlay:

- Removes `docker.sock` and `../:/workspace` from the backend
- Sets `APP_DEBUG=false`, `BOSSKU_ALLOW_DOCKER_COMPOSE=false`
- Does not publish Postgres/Redis ports to the host

To mount a single project repo read-only in production, add a bind mount under `backend.volumes` in a local override file.

## TLS and reverse proxy

Terminate HTTPS with Caddy or nginx in front of:

- UI: port `28470` (frontend)
- API: port `28480` (nginx → backend)

Set `FRONTEND_URL` and `CORS_ORIGINS` to your public HTTPS origin.

## Optional API token (no login)

In `app/.env`:

```env
BOSSKU_API_AUTH_ENABLED=true
BOSSKU_API_TOKEN=your-long-random-secret
```

For the Nuxt UI (docker-compose `frontend` service env or host build):

```env
NUXT_PUBLIC_API_TOKEN=your-long-random-secret
```

Clients must send `Authorization: Bearer <token>` or `X-Bossku-Token`.

## Rate limits

`BOSSKU_RUNS_RATE_PER_MINUTE` (default `60`) limits run/stream endpoints per IP.

## Health

- Laravel: `GET /up`
- Ollama: `GET /api/health/ollama` (no token required)

## Security checklist

- [ ] `BOSSKU_API_AUTH_ENABLED=true` with a strong token
- [ ] `APP_DEBUG=false`
- [ ] Strong Postgres password (not `bossku`)
- [ ] TLS enabled
- [ ] No docker.sock on backend
- [ ] Firewall: only 443 (and SSH) public
