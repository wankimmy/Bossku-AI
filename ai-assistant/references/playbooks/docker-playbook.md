# Docker Playbook

Use this when creating or reviewing Dockerfile and Docker Compose setup for a project.

## Workflow

1. Read the app stack, package manager, lockfiles, start/build/test commands, ports, and required services.
2. Decide whether the target is local development, production, or both.
3. Create or update `.dockerignore` so builds do not copy host dependencies, build output, logs, secrets, or `.git`.
4. Write the Dockerfile:
   - specific base image
   - `WORKDIR`
   - lockfiles copied before source
   - dependency install in the image
   - source copy
   - build step if required
   - non-root user when practical
   - `EXPOSE`
   - simple `CMD`/`ENTRYPOINT`
5. Write Compose:
   - `build:` for local app image
   - `env_file: .env`
   - variableized ports
   - bind mount for source sync in development
   - named volumes for dependencies, databases, uploads, and caches
   - explicit top-level `networks:`
   - health checks and startup ordering where useful
6. Add `.env.example` with placeholders and ensure `.env` is gitignored.
7. For secrets, prefer Compose `secrets:` at runtime and BuildKit secret mounts at build time.
8. Verify:
   - `docker compose config`
   - `docker compose up -d --build` for first build
   - normal repeat startup with `docker compose up -d`
   - `docker compose ps`
   - logs and health checks
   - local file changes sync into the container
   - no secrets in committed files

## Output expectation

- one-command startup contract
- Dockerfile responsibility summary
- Compose service/network/volume map
- `.env` and secret handling notes
- local sync behavior
- verification commands and residual gaps
