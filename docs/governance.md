# Governance

BosskuAI includes a governance layer that classifies the risk level of every planned step before execution begins. High-risk and critical steps require explicit approval. The governance system is configurable to match your organization's risk tolerance.

## RiskClassifier Levels

Every step in an execution plan is assigned one of four risk levels by `RiskClassifier`:

| Level | Meaning | Default behavior |
|---|---|---|
| `low` | Read-only operations, non-destructive writes, queries | Auto-execute |
| `medium` | Writes to application code, config file changes, API calls to internal services | Auto-execute (logged) |
| `high` | Terminal commands, external HTTP calls, env file modifications | Requires approval |
| `critical` | Deployments, secret rotation, database drops, auth system changes | Always requires approval |

Risk level is determined by the step's `type` and content. The `RiskClassifier` combines:

1. **Type-based rules** — `terminal_command` is always at least `high`; `file_read` is always `low`
2. **Pattern detection** — regex and keyword patterns applied to step content (e.g. `DROP TABLE`, `rm -rf`, references to `.env` or `secrets`)
3. **Context rules** — e.g. a file write is `medium` normally, but `high` if the file is in a path matching configured sensitive path patterns

## Pattern Detection

`RiskClassifier` maintains a set of risk patterns in the `risk_patterns` table. Each pattern has:

- `pattern` — a regex applied to the step content
- `match_level` — the risk level to assign if the pattern matches
- `reason` — a human-readable explanation
- `override_type_level` — if true, this pattern can escalate a step beyond its type-based level

Examples of built-in patterns:

| Pattern | Level | Reason |
|---|---|---|
| `\bDROP\s+TABLE\b` | `critical` | Irreversible database destruction |
| `\brm\s+-rf\b` | `critical` | Irreversible file deletion |
| `\.env\b` | `high` | Environment file modification |
| `\bsudo\b` | `high` | Privilege escalation |
| `\bdocker\s+push\b` | `high` | Image publishing |
| `\bheroku\s+release\b` | `critical` | Production deployment |

## How the Existing RiskRuleEngine Is Extended

`RiskRuleEngine` is the evaluator that applies patterns to steps. To add custom rules:

1. Navigate to `/settings/governance`
2. Click **Add Rule**
3. Enter the regex pattern, assign a risk level, add a description
4. Save — rules are applied immediately to new runs (not retroactively)

Rules are evaluated in priority order (configurable). When multiple rules match a step, the highest resulting risk level wins.

You can also add rules programmatically by inserting into the `risk_patterns` table directly, or via the API:

```http
POST /api/governance/rules
Content-Type: application/json

{
  "pattern": "\\bterraform\\s+apply\\b",
  "match_level": "critical",
  "reason": "Infrastructure changes require manual review",
  "override_type_level": true
}
```

## Governance Rule Editor

The governance rule editor at `/settings/governance` shows:

- All active rules, categorized by risk level
- A test panel: paste any step content and see which rules match and what level is assigned
- Rule enable/disable toggles (disabled rules are preserved but not evaluated)
- Import/export for rules as JSON (useful for sharing a rule set across environments)

Built-in rules (shipped with BosskuAI) are shown with a lock icon — they can be disabled but not deleted. Custom rules can be fully edited or deleted.

## Audit Trail

Every governance decision is logged to `governance_events`:

- Which rule(s) fired
- The assigned risk level
- Whether execution was auto-approved, manually approved, or rejected
- Who approved (for manual approvals)
- Timestamp

This audit trail is queryable and is included in run exports. It is the evidence trail for compliance and incident post-mortems.
