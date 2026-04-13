# Docker Checklist

> If the request is general, ambiguous, or touches many files, ask clarifying yes/no questions before acting.

- Can the user start the stack with `docker compose up -d` after setup?
- Does the `Dockerfile` own dependency install, build steps, runtime defaults, and app startup?
- Are Compose `command:` overrides short and intentional, not hidden install/build scripts?
- Are credentials absent from `compose.yaml`, Dockerfile, shell scripts, committed `.env.example`, logs, and image layers?
- Is `.env` used for local non-secret config and ignored by git?
- Are true secrets passed through Docker secrets or BuildKit secret mounts instead of `ARG`/`ENV`?
- Are local source files synced to the container with bind mounts or an explicit Compose watch plan?
- Are dependency/cache/persistent directories protected with named volumes when a bind mount would hide container-installed files?
- Are top-level `networks:` and `volumes:` declared explicitly?
- Are service-to-service URLs using Compose service names and container ports, not container IPs?
- Are health checks, ports, `depends_on`, and restart behavior intentional?
- Is the container non-root where practical and free of privileged/socket/host-network access unless justified?
- Did verification include `docker compose config`, build/up/logs/health, and a quick secret scan?
