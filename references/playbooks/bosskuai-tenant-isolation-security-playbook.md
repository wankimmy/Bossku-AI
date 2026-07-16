# Bosskuai Tenant Isolation Security Playbook

## Purpose

Use this for multi-tenant data isolation, organization scoping, cross-tenant leaks, authorization boundaries, row-level access, and SaaS tenant security review.

## Operating Principles

- Treat any cross-tenant data exposure as high-severity security incident.
- Check every query, relation, policy, cache key, job, export, webhook, and report for tenant scope.
- Require server-side tenant checks; never trust client-provided tenant_id.
- Add regression tests for same-user/different-tenant and same-tenant/different-role cases.
- Review logs and audit trail for exposure window and affected records.

## Review Flow

1. Define the user/business impact.
2. Identify the trust boundary, data boundary, cost boundary, or operational boundary.
3. Inspect the smallest source-of-truth files first.
4. Propose the smallest safe change.
5. Add verification: test, metric, log, alert, rollback trigger, or customer signal.
6. Save durable learning only when it changes future behavior.

## Anti-patterns

- Optimizing a non-measured problem.
- Making broad architecture claims without repo evidence.
- Skipping rollback, audit, or support recovery.
- Storing secrets, temporary instructions, or untrusted claims in memory.
- Using generic SaaS advice without product-stage context.

## Done Bar

- Clear recommendation.
- Concrete implementation or SOP.
- Verification path.
- Main risk and rollback.
- Memory/handoff updated when useful.
