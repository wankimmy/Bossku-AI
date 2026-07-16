---
name: bosskuai-tenant-isolation-security
description: Use this for multi-tenant data isolation, organization scoping, cross-tenant leaks, authorization boundaries, row-level access, and SaaS tenant security review.
---

# Bosskuai Tenant Isolation Security

Use this for multi-tenant data isolation, organization scoping, cross-tenant leaks, authorization boundaries, row-level access, and SaaS tenant security review.

## Fast Path

1. Treat any cross-tenant data exposure as high-severity security incident.
2. Check every query, relation, policy, cache key, job, export, webhook, and report for tenant scope.
3. Require server-side tenant checks; never trust client-provided tenant_id.
4. Add regression tests for same-user/different-tenant and same-tenant/different-role cases.

## Default Checks

- Treat any cross-tenant data exposure as high-severity security incident.
- Check every query, relation, policy, cache key, job, export, webhook, and report for tenant scope.
- Require server-side tenant checks; never trust client-provided tenant_id.
- Add regression tests for same-user/different-tenant and same-tenant/different-role cases.
- Review logs and audit trail for exposure window and affected records.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-tenant-isolation-security-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-tenant-isolation-security-playbook.md`
- `../../references/checklists/tenant-isolation-security-checklist.md`
