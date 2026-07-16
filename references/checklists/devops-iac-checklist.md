# DevOps / IaC Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is the build path reproducible from source to artifact?
- Do CI/CD checks fail early and gate risky changes appropriately?
- Are artifacts immutable, traceable, and deployable across environments?
- Are containers, runtime config, health checks, and rollback paths explicit?
- Are IaC plans reviewed, state managed safely, and drift detectable?
- Are secrets, runners, modules, and third-party actions least-privilege and supply-chain-aware?
- Are logs, metrics, alerts, and recovery steps sufficient for safe operations?
