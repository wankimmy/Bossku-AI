---
name: bosskuai-ci-cd-pipelines
description: "Use this for designing, speeding up, or fixing CI/CD pipelines — GitHub Actions (also GitLab CI, Bitbucket) workflow structure, job graphs and matrices, dependency and build caching, concurrency and cancellation, required checks and branch protection, environments with approvals, OIDC cloud auth, build-once artifact and image promotion, release tagging and changelogs, monorepo path filters, secrets hygiene, and flaky-check policy. Classifying a red run belongs to the vendored ci-triage skill; infrastructure to bosskuai-devops-iac; AWS deploy mechanics to bosskuai-aws-deployment."
---

# BosskuAI CI/CD Pipelines

Use this skill when the pipeline itself is the work: its shape, speed, gates, secrets, or the way it promotes a build to production.

## How this differs from nearby skills

- **`ci-triage`** (loop-engineering): classifies a failing run as flake, regression, env, or config; this skill designs the pipeline so those classes are rare and obvious.
- **`bosskuai-devops-iac`**: infrastructure, runtime, and rollback design; this skill owns the workflow files that drive them.
- **`bosskuai-github-workflow`**: GitHub operations (issues, PRs, releases, settings); this skill owns `.github/workflows`.
- **`bosskuai-aws-deployment`** / **`bosskuai-vps-docker-deployment`** / **`bosskuai-mobile-app-release`**: the deploy target's specifics; this skill supplies the stages around them.
- **`bosskuai-laravel-verification`**: which Laravel checks to run; this skill schedules them.

## Mindset

- The pipeline is code: reviewed, linted (`actionlint`), versioned, and tested by running it.
- Fast on pull requests, thorough on main, exhaustive nightly. A PR check over ~10 minutes gets ignored.
- Build once, promote the same artifact through environments; never rebuild for production.
- A flaky check is a broken check with a deadline, not a retry button.
- Secrets never reach logs, forks, or PR builds from untrusted branches.

## Pipeline shape

| Trigger | Jobs | Budget |
|---|---|---|
| Pull request | lint + typecheck + unit (parallel), integration with service containers, secret scan, dependency audit, build | ≤ 10 min p50 |
| Push to main | PR jobs + image/artifact build tagged with the git SHA + push to registry + deploy staging + smoke test | ≤ 20 min |
| Tag `v*` | changelog, GitHub release, deploy production behind an environment approval, post-deploy checks | approval-gated |
| Nightly | full E2E matrix, load or perf smoke, dependency update PRs, cache warm | unbounded |
| `workflow_dispatch` | manual ops: rollback to a given tag, re-run migrations, data fixes with inputs | logged |

## GitHub Actions rules that matter

- `permissions:` at workflow level set to `contents: read`; grant `id-token: write`, `packages: write`, or `deployments: write` only on the jobs that need them.
- `concurrency: { group: ${{ github.workflow }}-${{ github.ref }}, cancel-in-progress: true }` for PR workflows; never cancel-in-progress on deploy workflows.
- Pin third-party actions to a commit SHA with a version comment; let Dependabot bump them.
- `timeout-minutes` on every job; default is six hours.
- Cache keyed on the lockfile hash with `restore-keys` fallback: `actions/setup-node` with `cache: pnpm`, `actions/cache` for `~/.composer/cache`, `~/.cache/go-build` + `~/go/pkg/mod`, Playwright browsers, and Docker layers via `buildx` `cache-from/to: type=gha`.
- Matrix with `fail-fast: false` while diagnosing, `true` once stable; shard slow test suites by index.
- Monorepos: `dorny/paths-filter` or native `paths:` filters to run only affected packages; Turborepo/Nx remote cache for builds.
- Reusable workflows (`workflow_call`) and composite actions for anything copied twice.
- Environments (`staging`, `production`) with required reviewers, environment-scoped secrets, and deployment branch rules.
- Upload JUnit/Playwright reports as artifacts with short retention; annotate failures inline.
- `pull_request_target` runs with secrets on fork code: avoid it, or never check out the PR head in it.
- Service containers (Postgres, MySQL, Redis) with health checks and tuned settings (`fsync=off` for Postgres in CI); warm the schema once per job, not once per test file.

## Required checks and merge policy

- Branch protection or rulesets: required status checks, up-to-date branch or merge queue, linear history or squash merges, signed commits where policy requires, CODEOWNERS review.
- Merge queue for repos with more than a handful of daily merges; it keeps main green without "update branch" churn.
- Name required checks stably (a job rename silently drops the protection).

## Speed

- Measure first: `gh run list --json durationMs` or the Actions usage report; find the p50 PR pipeline time and the longest job.
- Parallelize independent jobs; shard tests; cache dependencies and build outputs; use larger runners only for the one job that needs them.
- Skip work that cannot matter: docs-only changes, unaffected packages, draft PRs (`if: github.event.pull_request.draft == false` for heavy jobs).
- Self-hosted runners only for hardware, network, or licensing reasons; they are an operations commitment.

## Flakiness policy

- Every flaky test gets an issue, an owner, and a quarantine expiry; quarantined tests run but do not block.
- Retries are allowed only for the quarantined set and are counted; a retry that passes still records the failure.
- Root causes are almost always shared state, real time, network, or unordered assertions; fix those rather than adding sleeps.

## Secrets and supply chain

- OIDC federation to AWS/GCP/Azure instead of stored access keys; role limited to what the deploy job does.
- Environment-scoped secrets; `::add-mask::` for derived values; never `echo` a secret or pass it as a command argument.
- `gitleaks` or `trufflehog` on PRs; `npm audit`/`composer audit`/`govulncheck`/`pip-audit` on a schedule with a policy for what fails the build.
- SBOM generation and image scanning on push (Trivy, ECR scan) for anything shipped.

## Promotion and rollback

- Artifact or image built once on main, tagged with the SHA, promoted by retagging or by environment deploys of the same digest.
- Migrations run as a distinct, gated step and must be backward compatible with the running version.
- Post-deploy smoke: health endpoint, one authenticated request, one queue job.
- Rollback is a `workflow_dispatch` that deploys a previous tag; rehearse it.

## Stack presets

- **Laravel**: PHP matrix, `composer` cache, Pint, PHPStan, Pest with a Postgres/MySQL service, `php artisan config:cache` in the build image, frontend build in a separate job.
- **Nuxt / React**: pnpm store cache, typecheck, ESLint, Vitest, Playwright with browser cache, build with bundle-size check.
- **Go**: module and build cache, `golangci-lint` action, `go test -race`, `govulncheck`, static binary artifact.
- **Expo**: `eas build` with `EXPO_TOKEN`, preview builds per PR, production on tag; see `bosskuai-mobile-app-release`.

## Verification

```bash
actionlint
gh workflow list && gh run list --limit 20
gh run watch <id> --exit-status
```

## Guardrails

- Do not rebuild for production; promote the artifact built on main.
- Do not use unpinned third-party actions or `pull_request_target` with PR-head checkout.
- Do not add blanket retries to hide flakiness.
- Do not deploy from a workflow without an environment gate and a rollback path.
- Do not put secrets in workflow files, job outputs, or artifact contents.

## Output format

```text
Platform: [GitHub Actions | GitLab CI | ...] - Repo shape: [single | monorepo]
Current pipeline: [triggers → jobs → time p50]
Findings:
  P0/P1/P2 - [workflow/job] - [issue] - [fix]
Target pipeline: [triggers → jobs → gates → promotion]
Secrets/auth: [OIDC role, scoped secrets]
Speed plan: [caches, sharding, filters, expected time]
Flaky policy: [quarantine list, owners]
Rollback: [workflow, tested on <date>]
```

## References

- `../../references/checklists/ci-cd-pipelines-checklist.md`
- `../../references/checklists/devops-iac-checklist.md`
- `../../references/checklists/github-workflow-checklist.md`
