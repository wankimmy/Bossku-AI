# Hostinger Hosting Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is the product identified (shared, cloud, KVM VPS) and are its limits understood before planning daemons or Node processes?
- Was the real SSH port confirmed with `ss -tlnp` rather than the welcome email?
- Is root login disabled, password auth disabled, a keyed deploy user in place, and UFW plus the hPanel firewall limited to SSH/80/443?
- Are fail2ban and unattended-upgrades running, and is there swap on small plans?
- Is every domain listed with root, process, port, and health URL, and does each certificate carry the SANs it needs?
- Do Node apps run under PM2 as a non-root user with `pm2 startup` and the correct entrypoint?
- Is MySQL bound to localhost, and do generated passwords satisfy `validate_password`?
- Are `.env` files `640`, owned by the app user, and free of unverified rotated credentials?
- Are off-box, encrypted, nightly backups of the database and uploads in place, with a restore drill date recorded?
- Is there a zero-downtime deploy path (symlinked releases or `pm2 reload`) and a `config:clear && config:cache` step after env changes?
- If the box was ever compromised: is evidence preserved, persistence checked (uid-0 users, all crontabs, cron.d, systemd, authorized_keys, ld.so.preload, immutable attrs, typosquat binaries), all credentials rotated and verified, and a rebuild scheduled with a date?
- Is Cloudflare or another edge in front for TLS, caching, and origin hiding where uptime matters?
- Is the abuse-ticket reply drafted with findings, removals, and rebuild status?
