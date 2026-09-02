---
name: bosskuai-aws-deployment
description: "Use this for deploying and operating on AWS — choosing among ECS Fargate, App Runner, Lambda, EC2, EKS and Amplify; VPC and subnets, ALB and CloudFront, RDS/Aurora, S3, ElastiCache, SQS, SES; IAM least privilege, Secrets Manager and SSM, Terraform or CDK, GitHub Actions OIDC deploys, CloudWatch monitoring, backups and DR, and cost guardrails including the Malaysia and Singapore regions. Generic pipeline design belongs to bosskuai-ci-cd-pipelines; a single server to bosskuai-vps-docker-deployment."
---

# BosskuAI AWS Deployment

Use this skill when the target is AWS and the answer depends on which managed service to use, how to wire IAM and networking safely, and how to deploy and roll back without a console click.

## How this differs from nearby skills

- **`bosskuai-devops-iac`**: platform-agnostic delivery principles; this skill maps them to concrete AWS services.
- **`bosskuai-ci-cd-pipelines`**: the pipeline stages; this skill supplies the AWS deploy step, OIDC role, and rollback mechanics.
- **`bosskuai-vps-docker-deployment`** / **`bosskuai-hostinger-hosting`**: one box; use this skill when you need managed databases, autoscaling, or compliance boundaries.
- **`bosskuai-cost-optimization`**: cross-vendor spend; this skill bakes the AWS-specific cost traps into the design.
- **`bosskuai-observability-sre`**: SLOs and alerting strategy; this skill implements them with CloudWatch.

## Mindset

- Managed over self-run: RDS, not Postgres on EC2; SQS, not a Redis queue you babysit, unless the app already depends on it.
- IAM is the blast radius. Roles per workload, no long-lived access keys, OIDC from CI.
- Everything through IaC with plan review; the console is for reading.
- One AWS account per environment under Organizations; guardrails (SCPs, budgets, CloudTrail, GuardDuty) before the first workload.
- Region choice is a compliance and latency decision: `ap-southeast-5` (Malaysia) for PDPA data residency, `ap-southeast-1` (Singapore) for the broadest service catalog; confirm the needed services exist in the chosen region.

## Compute decision table

| Workload | Pick | Because |
|---|---|---|
| Container web app, small team, minimal ops | App Runner | Build-to-URL with autoscaling and TLS; limited networking control |
| Web services, workers, schedulers with predictable traffic | ECS Fargate | Containers without node management; sidecars, long-running tasks, private networking |
| Event-driven, spiky, short jobs | Lambda | Pay per invocation; watch cold starts, 15-minute limit, DB connection storms (use RDS Proxy) |
| Static or SSR frontend (Next.js, Nuxt) | Amplify Hosting or S3 + CloudFront | CDN-first, preview branches |
| Special hardware, licensing, legacy binaries, steady high utilization | EC2 (with ASG) | Only when containers cannot express it |
| Many teams, existing Kubernetes skills, multi-cluster scale | EKS | Otherwise its operational cost outweighs the benefit |

## Reference architecture for a Laravel / Nuxt / Node / Go SaaS

Route 53 → CloudFront (static, WAF) → ALB (TLS from ACM) → ECS Fargate services (`web`, `worker`, `scheduler`) in private subnets → RDS PostgreSQL or MySQL Multi-AZ in isolated subnets, ElastiCache Redis, SQS queues, S3 for uploads (private, presigned URLs), SES for email, ECR for images, Secrets Manager for credentials injected as task secrets, CloudWatch for logs and alarms. VPC endpoints for S3, ECR, Secrets Manager, and CloudWatch cut NAT gateway cost.

## Networking and security

- VPC across 2–3 AZs: public subnets only for ALB and NAT, private for compute, isolated (no route to NAT) for data stores.
- Security groups reference other security groups, not CIDRs; nothing on the database SG from `0.0.0.0/0`.
- TLS at CloudFront and ALB with ACM; HTTP redirects to HTTPS; WAF managed rule sets plus rate limiting on login and API paths.
- IAM: task execution role (pull image, read secrets) separate from task role (app permissions); scoped resource ARNs; permission boundaries for CI roles.
- CI deploys through an OIDC trust to a deploy role limited to ECR push, task definition registration, and service update.
- Account guardrails: root MFA, CloudTrail organization trail, GuardDuty, Config rules for public S3 and open SGs, AWS Budgets alerts at 50/80/100%.

## Data

- RDS Multi-AZ for production, automated backups with PITR, snapshots copied cross-region for DR, deletion protection on.
- Aurora Serverless v2 when load is variable; RDS Proxy in front of Lambda or bursty pools.
- Parameter groups for `max_connections`, slow query logging; Performance Insights on.
- S3: block public access, versioning on buckets that hold user data, lifecycle rules to Intelligent-Tiering, SSE-KMS.
- Encryption at rest with KMS everywhere; key policies reviewed like IAM.

## Deploy and rollback

1. CI builds the image, tags it with the git SHA (immutable), pushes to ECR with scan-on-push.
2. Run migrations as a one-off ECS task from the new image, gated by a plan step; migrations must be backward compatible with the currently running version (expand/contract).
3. Register a new task definition and update the service: rolling with minimum healthy 100% and circuit breaker on, or blue/green through CodeDeploy for high-risk services.
4. ALB health checks decide readiness; alarms on 5xx and p99 gate the promotion.
5. Rollback is re-deploying the previous task definition revision; the previous image stays in ECR (lifecycle policy keeps the last N).
6. Terraform: remote state in S3 with DynamoDB locking, `plan` on pull requests, `apply` from main with a manual approval environment; CDK: `cdk diff` in PR, `cdk deploy` with `--require-approval` for IAM changes.

## Observability

- CloudWatch Logs with explicit retention (never "never expire"), structured JSON, log groups per service.
- Alarms: ALB 5xx rate, target response time p99, ECS CPU/memory, RDS CPU, free storage, connections, replica lag, SQS oldest message age and DLQ depth, Lambda errors and throttles.
- Tracing with X-Ray or OpenTelemetry to CloudWatch; dashboards per service; alarm actions to SNS → Slack or PagerDuty.

## Cost guardrails

- NAT gateway data processing is the usual surprise bill; VPC endpoints and Fargate ARM (Graviton) images are the first two savings.
- Right-size Fargate tasks from CloudWatch metrics; buy Savings Plans only after a month of steady state.
- Schedule dev and staging environments off outside working hours; delete unattached EBS volumes and Elastic IPs.
- Tag everything (`env`, `service`, `owner`) and enforce with tag policies so Cost Explorer can attribute spend.

## Guardrails

- Do not create IAM users with access keys for applications or CI; roles and OIDC only.
- Do not expose RDS, ElastiCache, or OpenSearch to the internet, even "temporarily".
- Do not apply infrastructure changes without a reviewed plan or diff.
- Do not run production migrations from a laptop; use the pipeline's one-off task.
- Do not put secrets in task definition environment variables, Dockerfiles, or Terraform variables committed to git.

## Output format

```text
Target: [account/env] - Region: [...] - Compute: [choice + why]
Topology: [edge → LB → compute → data; queues, cache, storage]
IAM and network boundaries: [roles, SGs, endpoints]
Deploy path: [build → ECR → migrate → service update → health gate]
Rollback: [previous task def / snapshot restore]
Observability: [alarms, dashboards]
Cost notes: [top 3 traps addressed]
IaC: [Terraform | CDK] - plan reviewed: [yes/no]
```

## References

- `../../references/checklists/aws-deployment-checklist.md`
- `../../references/checklists/devops-iac-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
