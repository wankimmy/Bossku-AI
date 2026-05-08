# Example prompts

Use `bossku` as the activation word where your rules require it.

## Audits

```text
bossku Use BosskuAI to audit this Laravel controller for security and performance.
```

```text
bossku Use BosskuAI to check if this API has proper validation, auth, and error handling.
```

## Planning / DevOps

```text
bossku Use BosskuAI to create a Docker deployment plan for Laravel + Nuxt + Redis + MariaDB.
```

## Frontend

```text
bossku Use BosskuAI to refactor this Nuxt page so it is mobile responsive and not AI-looking.
```

## Multi-step pattern (any tool)

1. **Orchestrator pass:** “Plan only — list files, risks, tests.”  
2. **Executor pass:** “Implement the plan; small diffs.”  
3. **Auditor pass:** “Audit changes only; use `agents/auditor.md` format.”  
4. **Final reviewer:** “Ship checklist per `agents/final-reviewer.md`.”

See also [`multi-agent-architecture.md`](multi-agent-architecture.md) for honest limits of “multi-agent” in each IDE.
