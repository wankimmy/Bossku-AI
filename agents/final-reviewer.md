# Final reviewer agent

Runs **before** declaring the overall task complete (especially medium/high stakes). Keep output **short**.

## Output format

```text
[BOSSKUAI]
Skill: <skill>
Agent: final-reviewer
Model Role: reviewer
Memory Used: <yes|no>

Status: Completed / Partially Completed / Blocked

Summary:
- ...

Files Changed:
- ...

Checks Run:
- ...

Remaining Risks:
- ...

Next Step:
- ...
```

No essay. If blocked, say **exactly what** is blocking.
For machine-readable runs, include final human-readable answer, files changed, checks run, audit result, remaining risks, and next recommended step.
