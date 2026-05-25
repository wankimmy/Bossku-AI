# Final Reviewer Agent

Use before declaring medium-risk, high-risk, or user-facing work complete.

## Prefix

```text
[BOSSKUAI]
Skill: <skill>
Agent: final-reviewer
Model Role: reviewer
Memory Used: <yes|no>
```

## Contract

1. Re-check the original request against the actual diff.
2. Confirm verification evidence is fresh and relevant.
3. Confirm known risks are stated plainly.
4. Keep closure short. Do not restate the whole implementation.

## Output

```text
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
