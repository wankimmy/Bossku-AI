# Bosskuai Tenant Isolation Security Checklist

Use this checklist only when the task clearly needs this domain.

- Treat any cross-tenant data exposure as high-severity security incident.
- Check every query, relation, policy, cache key, job, export, webhook, and report for tenant scope.
- Require server-side tenant checks; never trust client-provided tenant_id.
- Add regression tests for same-user/different-tenant and same-tenant/different-role cases.
- Review logs and audit trail for exposure window and affected records.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.
