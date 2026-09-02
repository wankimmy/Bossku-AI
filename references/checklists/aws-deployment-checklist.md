# AWS Deployment Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is the region chosen deliberately (data residency, service availability, latency), and do all needed services exist there?
- Are environments in separate accounts under Organizations with root MFA, CloudTrail, GuardDuty, and Budgets alerts?
- Is compute chosen from the decision table with a stated reason, not by habit?
- Are compute tasks in private subnets, data stores in isolated subnets, and only ALB/NAT public?
- Do security groups reference security groups, with no database access from `0.0.0.0/0`?
- Are task execution and task roles separate, resource-scoped, and free of wildcard actions?
- Does CI deploy through an OIDC-federated role with no long-lived access keys?
- Are secrets in Secrets Manager or SSM and injected at runtime, never in task env, Dockerfiles, or git?
- Is TLS from ACM enforced at CloudFront and ALB, with WAF and rate limits on auth and API paths?
- Is RDS Multi-AZ with PITR, deletion protection, and cross-region snapshot copies?
- Are S3 buckets blocking public access, versioned where user data lives, encrypted with KMS, and lifecycle-managed?
- Are images tagged immutably with the git SHA and scanned on push?
- Do migrations run as a gated one-off task and stay backward compatible with the running version?
- Is the service update rolling with circuit breaker (or blue/green), with health checks and alarms gating promotion?
- Is rollback a previous task definition revision, and is it rehearsed?
- Is IaC state remote and locked, with plan/diff reviewed in the PR and apply gated on main?
- Do alarms cover 5xx, p99 latency, CPU/memory, RDS storage/connections, queue age, and DLQ depth?
- Are log retention, VPC endpoints, Graviton, and off-hours schedules in place to cap cost, and is everything tagged?
