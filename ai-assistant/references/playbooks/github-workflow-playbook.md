# GitHub Workflow Playbook

Use this for GitHub issues, pull requests, Actions, releases, dependency alerts, and repo workflow coordination.

## Workflow

1. Identify the GitHub object and whether local or remote state is authoritative.
2. Read local repo state first: branch, status, recent commits, CI files, and contribution docs.
3. Use GitHub MCP or `gh` when remote facts matter; separate confirmed facts from inference.
4. For issues, create durable acceptance criteria, labels, dependencies, and owner context.
5. For PRs, check scope, unrelated changes, risk areas, tests, rollback, CI, conflicts, and unresolved comments.
6. For Actions, inspect the first failing step and classify the failure before suggesting reruns.
7. For releases or dependency updates, compare user impact, compatibility risk, artifacts, and verification evidence.

## Output expectation

- object and source-of-truth summary
- current confirmed state
- recommended action with owner/risk
- PR or issue text when requested
- verification and remaining questions
