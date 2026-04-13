---
name: bosskuai-docker
description: Use this for Dockerfile and Docker Compose setup or review, including one-command `docker compose up -d` startup, `.env` configuration, no hardcoded credentials in YAML, bind-mounted local volume sync, explicit networks, service health checks, non-root images, and containerized dev/prod workflows.
---

# BosskuAI Docker

Use this skill when the task involves Dockerizing an app, creating or reviewing `Dockerfile`, `compose.yaml`, `.dockerignore`, `.env.example`, service networks, volumes, local development sync, or "make this run with only Docker Compose".

## How this differs from nearby skills

- **`bosskuai-devops-iac`**: owns CI/CD, deployment flow, IaC, and operational platform risk. This skill owns concrete Dockerfile/Compose implementation.
- **`bosskuai-cybersecurity-risk`**: load alongside this skill when container config touches secrets, public ports, auth, PII, or production infrastructure.
- **`bosskuai-engineering-delivery`**: owns feature implementation and verification; this skill owns container runtime packaging and startup contract.
- **`bosskuai-performance-profiling`**: load when image build time, hot reload, filesystem sync, or runtime resource use is the main problem.

## Non-negotiable Bossku Docker contract

- User startup must be one command: prefer Docker Compose V2, `docker compose up -d`; support legacy `docker-compose up -d` only when the repo already uses it.
- Put install/build/runtime setup in the `Dockerfile`, not as long `command:` shell scripts in Compose.
- Compose owns wiring: services, build args, ports, `env_file`, volumes, networks, health checks, dependencies, and named volumes.
- Do not hardcode credentials, tokens, private URLs, or passwords in `compose.yaml`, Dockerfile, scripts, or committed `.env` files.
- Use `.env` or `env_file` for non-secret configuration; use Docker secrets or BuildKit secret mounts for real secrets.
- Include `.env.example` with placeholder values and commit that, not `.env`.
- Keep the local workspace synced with the container for development using a bind mount such as `.:/app`, with named volumes for dependency/cache directories that should not be overwritten.
- Include explicit top-level `networks:` and attach services intentionally, even though Compose creates a default network.
- Include top-level `volumes:` for persistent data such as databases, uploads, and dependency caches.

## Workflow

### Phase 1 - Read the app

1. Identify stack, package manager, start command, build command, test command, ports, database/cache dependencies, and required env vars.
2. Read existing Docker files before replacing them.
3. Decide if the target is local development, production image, or both.
4. Decide what must sync live from host to container and what must remain container-owned.

### Phase 2 - Dockerfile

5. Use a specific base image suitable for the stack; avoid `latest`.
6. Set `WORKDIR`.
7. Copy lockfiles before source to preserve dependency cache.
8. Install dependencies inside the image.
9. Copy source after dependencies.
10. Build inside the image when the app has a build step.
11. Run as a non-root user where practical.
12. Expose the app port for documentation and tooling.
13. Use `CMD` or `ENTRYPOINT` so Compose does not need a long shell command.
14. Use multi-stage builds when it reduces runtime image size or separates dev/build/prod concerns.

### Phase 3 - Compose

15. Use `compose.yaml` or the repo's existing Compose filename convention.
16. Add app service `build:` with context and Dockerfile.
17. Add `env_file: .env` and keep YAML values as variable references, not literal secrets.
18. Add ports with variables where useful, for example `"${APP_PORT:-3000}:3000"`.
19. Add bind mounts for local source sync and named volumes for dependency folders or persistent data.
20. Add explicit service network names under top-level `networks:`.
21. Add health checks for app and data services where a meaningful check exists.
22. Use `depends_on` only for startup ordering; do not treat it as proof a service is ready unless health conditions are configured.
23. Keep the default user command simple: after files are present, the user should only need `docker compose up -d`.

### Phase 4 - Env and secrets

24. Create/update `.env.example` with safe placeholders.
25. Ensure `.env` is ignored by git.
26. Use `env_file` for normal configuration.
27. Use Compose `secrets:` for passwords, certificates, and API keys where supported by the app.
28. Use BuildKit `RUN --mount=type=secret` for build-time secrets; never use `ARG` or `ENV` for build secrets.

### Phase 5 - Verify

29. Validate YAML and Dockerfile syntax.
30. Run `docker compose config` when Docker is available.
31. Run `docker compose up -d --build` for first build if needed, then confirm normal startup works with `docker compose up -d`.
32. Check `docker compose ps`, logs, health checks, app URL, and volume sync.
33. Confirm no secrets appear in YAML, image layers, logs, git diff, or `.env.example`.

## Starter Compose pattern

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    env_file:
      - .env
    ports:
      - "${APP_PORT:-3000}:3000"
    volumes:
      - .:/app
      - app_node_modules:/app/node_modules
    networks:
      - app_network
    depends_on:
      - db

  db:
    image: postgres:16
    env_file:
      - .env
    volumes:
      - db_data:/var/lib/postgresql/data
    networks:
      - app_network

volumes:
  app_node_modules:
  db_data:

networks:
  app_network:
    driver: bridge
```

Adapt this pattern to the stack. Do not copy Node-specific `node_modules` volumes into non-Node projects.

## Guardrails

- Do not put credentials directly in YAML, Dockerfile, shell scripts, or `.env.example`.
- Do not commit `.env`; add it to `.gitignore` when missing.
- Do not use bind mounts for production deployment unless that is explicitly required.
- Do not use a broad bind mount that hides installed container dependencies without adding named volumes for dependency directories.
- Do not leave privileged mode, host networking, Docker socket mounts, or root users enabled unless there is a documented reason.
- Do not make the user run manual install commands on the host after Dockerization.

## Output format

```text
Docker summary:
  Target: [dev / production / both]
  Startup: docker compose up -d
  Services: [app, db, cache, worker]
  Env/secrets: [.env, .env.example, secrets]
  Sync: [bind mounts / named volumes / compose watch]
  Network: [network name]

Files changed:
  [Dockerfile / compose.yaml / .dockerignore / .env.example / .gitignore]

Verification:
  [docker compose config / build / up / logs / health / sync / secret scan]
```

## References

- `../../references/checklists/docker-checklist.md`
- `../../references/playbooks/docker-playbook.md`
- `../../references/checklists/devops-iac-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
