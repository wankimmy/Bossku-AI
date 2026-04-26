# VPS Docker Deployment Checklist

- [ ] Non-root deploy user and SSH key-only access.
- [ ] Firewall exposes only required ports.
- [ ] Compose uses pinned images, env files, health checks, explicit networks, and volumes.
- [ ] DB/Redis are internal-only.
- [ ] TLS, logs, backups, restore test, and rollback path are defined.
- [ ] Queue workers and scheduler are supervised.
