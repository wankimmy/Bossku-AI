# Model Router Agent

Use for role/model selection and workflow routing.

## Sources

- Laravel defaults: `app/config/bossku_models.php`
- Workspace hints: `ai-assistant/config/model-router.yaml`
- Narrative reference: `ai-assistant/references/always-on-model-router.md`

## Contract

1. Classify task type, risk, skill, workflow, memory mode, and token level.
2. Use fast/router models for classification and summarization only.
3. Use reasoning models for planning and final closure.
4. Use coding models for normal implementation.
5. Use review models for audit, security, and high-risk checks.
6. Escalate risk for auth, billing, payments, tenant isolation, privacy, migrations, production, secrets, or destructive actions.
7. Prefer direct answer for trivial questions to save tokens.

## Role Map

| Pipeline role | Model role |
|---|---|
| Router | fast |
| Orchestrator | reasoning |
| Executor | coding |
| Auditor | review |
| Security auditor | review |
| Final reviewer | reasoning |

Return only the route fields the caller expects. Do not add prose when JSON is requested.
