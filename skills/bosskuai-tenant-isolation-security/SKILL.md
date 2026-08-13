---
name: bosskuai-tenant-isolation-security
description: Use this for multi-tenant data isolation, organization scoping, cross-tenant leaks, authorization boundaries, row-level access, and SaaS tenant security review.
---

# BosskuAI Tenant Isolation Security

Use this skill when the question is **whether tenant A can ever observe tenant B's data**, through any path.

## How this differs from nearby skills

- **`bosskuai-cybersecurity-risk`**: covers the whole threat surface; this skill covers only the tenant boundary.
- **`bosskuai-database-engineering`**: designs schema and indexes; this skill checks that every access path carries a tenant predicate.
- **`bosskuai-business-logic-review`**: validates workflow correctness; this skill assumes the workflow is right and asks who can see the result.
- **`bosskuai-laravel-security`**: Laravel-specific policy/gate mechanics; this skill is framework-neutral.

## Treat as high severity

Any confirmed cross-tenant read or write is an incident, not a bug ticket. Establish exposure window, affected tenants, and affected record counts before discussing the fix.

## Where isolation actually leaks

Check each path independently. A correct controller does not imply a correct export.

- **Queries and relations**: nested/eager loads and `whereHas` that scope the parent but not the child.
- **Direct object references**: any lookup by id that omits the tenant predicate.
- **Cache keys**: keys missing a tenant segment serve one tenant's payload to another.
- **Background jobs**: serialized job payloads that resolve tenant context at run time rather than enqueue time.
- **Exports, reports, invoices, PDFs**: batch paths that bypass the request-scoped tenant guard.
- **Webhooks and callbacks**: inbound handlers that trust a tenant id from the payload.
- **Search indexes**: shared indexes without a tenant filter applied at query time.
- **Aggregates and analytics**: counts and dashboards computed across the whole table.
- **File and object storage**: predictable paths or signed URLs without tenant checks.
- **Admin and impersonation**: elevated roles that silently widen scope.

## Guardrails

- Enforce tenant scope **server-side**. Never trust `tenant_id`, `org_id`, or a workspace slug supplied by the client.
- Prefer a default-deny mechanism (global scope, row-level security, repository base query) over remembering to filter at each call site.
- A missing tenant filter is not fixed by adding one filter; find the shared helper and fix the class of bug.
- Do not rely on UUIDs as a security control. Unguessable is not unauthorized.
- Do not close an isolation finding without a regression test.

## Required regression tests

Every fix adds at least these cases:

- Same user, different tenant: must be denied.
- Same tenant, different role: must respect role limits.
- Unauthenticated access to the same resource: must be denied.
- The batch path (job, export, or report) that mirrors the fixed request path.

## Output format

```text
Boundary: [what separates tenants: column, schema, database, index filter]
Enforcement: [where it is applied, and whether it is default-deny]

Findings:
  P0/P1/P2 - [path] - [how it leaks] - [fix]

Exposure (if a leak is confirmed):
  Window: [when it became reachable]
  Affected: [tenants / records, or how to determine]

Fix plan: [smallest change that closes the class, not the instance]
Regression tests: [cases added]
Verification: [what was actually run]
```

## References

- `../../references/checklists/tenant-isolation-security-checklist.md`
