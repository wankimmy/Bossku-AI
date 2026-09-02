# CI/CD Pipelines Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is the PR pipeline under ~10 minutes at p50, with lint, typecheck, unit, integration, secret scan, and build running in parallel where possible?
- Are `permissions:` minimal at workflow level, with `id-token`/`packages`/`deployments` granted only on the jobs that need them?
- Is `concurrency` cancelling superseded PR runs and never cancelling deploys?
- Are third-party actions pinned to commit SHAs and updated by Dependabot?
- Does every job have `timeout-minutes`?
- Are dependency, build, browser, and Docker layer caches keyed on lockfile or content hashes?
- Do monorepos run only affected packages, and is repeated YAML factored into reusable workflows or composite actions?
- Are `staging` and `production` environments gated with required reviewers and environment-scoped secrets?
- Is the artifact or image built once on main, tagged with the SHA, and promoted unchanged?
- Do migrations run as a gated, backward-compatible step before the service update?
- Is there a post-deploy smoke test and a rehearsed rollback workflow?
- Are required checks named stably and enforced by branch protection or a merge queue?
- Is cloud auth via OIDC with no stored long-lived keys, and are secrets never echoed or passed as arguments?
- Is `pull_request_target` avoided or hardened against fork code?
- Are flaky tests quarantined with owner and expiry, with retries limited to that set and counted?
- Are test reports uploaded as artifacts and failures annotated inline?
- Does `actionlint` pass?
