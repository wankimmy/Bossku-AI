---
name: bosskuai-github-workflow
description: Use this for GitHub workflow operations across issues, pull requests, Actions, releases, Dependabot, repository settings, and GitHub MCP-assisted project coordination.
---

# BosskuAI GitHub Workflow

Use this skill when the task involves **GitHub as the delivery surface**: issues, pull requests, reviews, Actions, releases, repo metadata, Dependabot, or GitHub MCP workflows.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: implements code changes; this skill coordinates the GitHub wrapper around that delivery.
- **`bosskuai-rigorous-code-review`**: reviews diffs; this skill prepares, routes, and follows up on PRs and review artifacts.
- **`bosskuai-devops-iac`**: designs CI/CD and infrastructure; this skill reads and operates the GitHub workflow surface.
- **`bosskuai-planning-execution`**: manages broader milestones; this skill translates work into GitHub issues, PRs, checks, and releases.

## Mindset

- GitHub is a collaboration system, not only a remote git host.
- The best PRs make reviewer context cheap: scope, rationale, risks, tests, and rollback are visible before the diff is opened.
- CI signal is only useful if failure ownership is clear.
- Issues should encode decisions, acceptance criteria, and dependencies, not just reminders.
- Release notes should explain user impact and operational risk, not list commits mechanically.

## MCP and CLI posture

Use GitHub MCP or `gh` when available for:

- Issue search, creation, triage, labels, milestones, and project fields
- PR creation, review summaries, comments, checks, merge readiness, and branch comparisons
- Actions run inspection, logs, reruns, artifacts, and workflow dispatch
- Release drafting, tag comparison, changelog checks, and asset verification
- Dependabot alert review and dependency update prioritization

If tooling is unavailable, degrade gracefully to local git history, repo files, and user-provided links. Do not invent remote state.

## Capability map

**Issues**
- Create acceptance criteria, labels, milestones, project fields, and dependency comments.
- Summarize linked PRs and identify stale or blocked work.

**Pull requests**
- Draft PR bodies, update descriptions, inspect checks, summarize review threads, and prepare merge readiness notes.
- Compare branch diff to issue scope and flag unrelated changes.

**Actions**
- Inspect workflow runs, isolate first failing step, classify flakes, and recommend rerun or fix.
- Connect CI failures back to the owning code or config change.

**Releases and dependencies**
- Draft release notes from tag comparisons and user-facing change groups.
- Triage Dependabot updates by exploitability, compatibility, and verification evidence.

## Workflow

### Phase 1 - Orient

1. Identify the GitHub object: issue, PR, workflow run, release, alert, repo setting, or project board.
2. Read the local repo state first: branch, status, recent commits, CI files, and contributing docs.
3. If remote access is needed, use GitHub MCP or `gh` to fetch the exact object state.
4. Separate confirmed remote facts from local inferences.

### Phase 2 - Issues and planning

5. Turn fuzzy asks into issue-ready acceptance criteria.
6. Check for duplicates, related issues, blocked dependencies, and existing labels or milestones.
7. Create or update issues only with enough context to be actionable: problem, desired outcome, constraints, and verification.
8. Keep issue bodies durable; keep transient status chatter in comments only when useful.

### Phase 3 - Pull requests

9. Before opening a PR, verify the branch contains only intended changes and no unrelated user work.
10. Draft the PR with summary, test evidence, risk areas, rollback, screenshots or logs when relevant, and linked issues.
11. Use confidence-scored review comments when summarizing findings: only state a bug as a bug when evidence is strong.
12. Check merge readiness: CI status, required reviews, conflicts, stale branches, and unresolved comments.
13. If asked to merge, confirm policy and ensure checks satisfy the repository's expectations.

### Phase 4 - Actions and CI

14. For failing Actions, identify the workflow, job, failing step, commit SHA, and whether the failure is deterministic.
15. Read logs around the first root failure, not only the final cascade.
16. Distinguish infra flake, dependency drift, test failure, secret/permission error, and code regression.
17. Recommend rerun only when the evidence points to flake or external instability.

### Phase 5 - Releases and dependencies

18. For releases, compare tags or commits, group changes by user impact, note breaking changes, and verify artifacts.
19. For Dependabot, identify exploitability, upgrade path, lockfile impact, and test coverage before merging.
20. Capture release and dependency risks in the PR or issue so future maintainers see the reasoning.

## Guardrails

- Do not claim remote GitHub state unless you read it through tooling or the user supplied it.
- Do not merge, close, label, or rerun remote workflows unless the user asked for that action or the repo workflow clearly requires it.
- Do not paste secrets, tokens, private URLs, or sensitive CI logs into issues or PRs.
- Do not bury failing tests behind a green summary; name what passed and what did not run.
- Do not change repository settings or branch protection without explicit approval.

## Output format

```text
GitHub workflow summary:
  Object: [issue / PR / Actions run / release / Dependabot / repo]
  Source of truth read: [local files / gh / GitHub MCP / user-provided]
  Current state: [confirmed facts]

Recommended action:
  [action] - [why] - [risk / owner]

PR or issue content:
  Summary: [short]
  Verification: [commands / checks]
  Risks: [known gaps]
  Rollback: [how to reverse]

Open questions:
  [what needs user or maintainer confirmation]
```

## References

- `../../references/checklists/github-workflow-checklist.md`
- `../../references/playbooks/github-workflow-playbook.md`
