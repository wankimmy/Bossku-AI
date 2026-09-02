---
name: bosskuai-hostinger-hosting
description: "Use this for Hostinger-hosted workloads — KVM VPS provisioning and hardening (SSH keys, UFW, fail2ban, Monarx), hPanel shared or cloud hosting (PHP selector, cron, Git deploy, SSL), Hostinger DNS and email routing, one-box nginx + PM2 + php-fpm + MySQL layouts, snapshots vs real backups, malware or botnet cleanup, abuse suspension recovery, and rebuild-vs-clean decisions. Docker-first single-server deploys belong to bosskuai-vps-docker-deployment; AWS to bosskuai-aws-deployment."
---

# BosskuAI Hostinger Hosting

Use this skill when the box or plan is Hostinger and the answer depends on what hPanel controls, what the VPS template gives you, and how Hostinger's abuse process behaves.

## How this differs from nearby skills

- **`bosskuai-vps-docker-deployment`**: containerized single-server deploys on any provider; use it for the compose topology, this skill for the Hostinger-specific provisioning, panel, backups, and incident realities.
- **`bosskuai-aws-deployment`**: managed services and multi-AZ; move there when you need managed databases, autoscaling, or compliance boundaries.
- **`bosskuai-incident-response`**: incident coordination; this skill has the concrete VPS compromise playbook.
- **`bosskuai-cybersecurity-risk`**: risk analysis; this skill applies it to a Hostinger box.

## Mindset

- A root-compromised server is not "cleaned"; it is contained until rebuilt from a clean template with data restored.
- Hostinger's abuse team suspends first (CPU abuse, outbound scans, spam) and asks later. Keep the box quiet and answer tickets with evidence.
- Snapshots are for rollback and get overwritten; they are not backups. Off-box, restore-tested copies are.
- Shared hosting and a KVM VPS are different products: hPanel owns the stack on shared, you own everything on the VPS.

## Product map

- **Shared / Cloud hosting (hPanel)**: PHP version selector, cron jobs, Git deployment, file manager, free SSL, LiteSpeed cache, limited SSH on higher plans. No root, no custom daemons, no Node long-running processes.
- **KVM VPS**: root on Ubuntu/Debian/AlmaLinux or an app template (Docker, n8n, CyberPanel), hPanel VPS firewall (in front of the OS firewall), snapshots, optional paid backups, Monarx malware scanner, browser terminal for lock-out recovery.
- **DNS zone editor, domains, email** (Hostinger mail or Titan): keep DNS TTLs short before migrations; confirm which cert carries the `www` SAN.

## First hour on a new VPS

1. Verify what actually listens: `ss -tlnp`. The SSH port in Hostinger's welcome email may not match `sshd_config`; trust the socket, not the email.
2. Create a `deploy` user with sudo; install your key; keep the current session open while testing a fresh login.
3. `sshd_config`: `PermitRootLogin no`, `PasswordAuthentication no`, `AllowUsers deploy`, then `sshd -t && systemctl reload ssh`.
4. UFW: allow OpenSSH, 80, 443 only; mirror the same in the hPanel VPS firewall.
5. `fail2ban` sshd jail, `unattended-upgrades`, timezone, hostname, 1–2 GB swap on small plans.
6. Enable paid backups or set up off-box backups before deploying anything.

## One-box layout that stays operable

- nginx vhost per domain in `sites-available`; certbot with explicit `-d` lists; run `certbot certificates` and check SANs per cert.
- Node apps under PM2 as the `deploy` user (never root) with `pm2 startup` + `pm2 save`; `ecosystem.config.js` names the real entrypoint (verify it matches `package.json`).
- php-fpm pool per site with its own user; Laravel `storage/` writable by that user only.
- MySQL bound to `127.0.0.1`; `validate_password` is MEDIUM on Hostinger images, so generated passwords need a special character or `ALTER USER` fails.
- Redis bound to localhost with a password; `.env` files `640`, owned by the app user.
- Keep one table in the repo docs: domain → root → process → port → health URL. Socket.io vhosts legitimately return 404 at `/`; health-check `/socket.io/?EIO=4&transport=polling` instead.

## Deploying without downtime on one box

- PHP: symlinked releases (`releases/<sha>` → `current`), `composer install --no-dev`, `php artisan migrate --force` gated, then swap the symlink and `php artisan config:clear && config:cache` (cached config keeps old credentials otherwise).
- Node: `pm2 reload <app>` in cluster mode, or blue/green ports behind nginx.
- Never rotate a credential into `.env` before the `ALTER USER` or provider change is verified; a half-rotation takes the app down.
- `artisan tinker` can fail when psysh cannot write its config dir; bootstrap Laravel from a standalone PHP script for one-off scripts.

## Backups

- Nightly `mysqldump --single-transaction` (or `pg_dump`) plus `restic`/`rclone` of uploads to S3-compatible or B2 storage off the box; retain 30 days; encrypt.
- Binlogs cover only the current instance and are not a backup.
- Restore drill quarterly into a scratch VPS; record the date in the runbook.

## Compromise and suspension playbook

1. **Signals**: unknown process at 100% CPU, Hostinger abuse email, outbound SSH scans, new users, zeroed binaries.
2. **Preserve**: snapshot first; copy evidence (`/var/log/auth.log`, crontabs, `last`, process list) to `/root/incident-<date>`.
3. **Contain**: kill by verified executable path, never `pkill -u` on an account that turns out to be uid 0. Check `/etc/passwd` for extra uid-0 users; check crontabs of every user, `/etc/cron.d`, systemd units and timers, `authorized_keys` of every user, `/etc/ld.so.preload`, and typosquat binaries in `/usr/bin` (`systemtd`).
4. **Immutable attributes**: `Operation not permitted` as root means `chattr +i`; `lsattr` then `chattr -i` before repair.
5. **Repair the OS**: zeroed `apt-get`/`python3`/`php-fpm` create a dpkg deadlock; extract packages with `dpkg-deb -x` from `/var/cache/apt/archives` to break it.
6. **Rotate everything**: root and user passwords, all keys, DB users, app secrets, API tokens; verify each before writing to `.env`.
7. **Decide**: any root compromise → rebuild from a clean template, restore data only, redeploy from git. "Contain now, rebuild later" without a date is how the second suspension happens.
8. **Reply to Hostinger** with what was found, what was removed, and what was rebuilt.

## Sizing and edge

- KVM 1–2 for staging; KVM 2+ (4 GB) for production with PM2 + MySQL + Redis; watch RAM before CPU.
- Cloudflare in front for DDoS, TLS, caching, and to hide the origin IP; keep origin certs valid anyway.
- No SLA on Hostinger VPS networking; if uptime is contractual, move the database or the whole stack to a managed provider.

## Verification

```bash
ss -tlnp
sshd -T | grep -Ei 'permitrootlogin|passwordauthentication|allowusers'
ufw status verbose && fail2ban-client status sshd
certbot certificates
pm2 ls && systemctl --failed
awk -F: '$3==0' /etc/passwd
ls -la /etc/cron.d && for u in $(cut -d: -f1 /etc/passwd); do crontab -l -u "$u" 2>/dev/null | sed "s/^/$u: /"; done
```

## Guardrails

- Do not leave `PermitRootLogin yes` with password auth on a public VPS, even for a day.
- Do not treat snapshots or binlogs as backups.
- Do not write rotated credentials into `.env` before the rotation is verified.
- Do not trust a previously root-compromised box after cleanup; schedule the rebuild with a date.
- Do not keep the SSH port, hostname, or credentials in a skill or memory that syncs anywhere shared.

## Output format

```text
Hostinger product: [shared | cloud | KVM VPS plan] - OS: [...]
Hardening state: [ssh, firewall, fail2ban, updates]
Layout: [domain → root → process → port → health]
Backups: [what, where off-box, last restore test]
Findings / blockers: [P0/P1/P2]
Deploy or recovery steps: [commands]
Rebuild decision: [not needed | scheduled <date> | done]
```

## References

- `../../references/checklists/hostinger-hosting-checklist.md`
- `../../references/checklists/vps-docker-deployment-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
