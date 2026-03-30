# BosskuAI Route Command

Use this command to run explicit task-to-skill routing before planning or implementation.

## Intent

- classify task intent quickly and consistently
- route cluster-first (9-way), then choose minimal skill set
- prevent over-loading too many skills
- produce a stable routing decision that can be reused

## Inputs

- user request
- current repo context (if any)
- optional constraints (time, risk, required output)

## Instructions

1. Parse the request and extract:
   - primary goal
   - deliverable type
   - risk level (low/medium/high)
   - ambiguity level (clear/partial/unclear)
2. Run the **Task classifier** from `AGENTS.md`.
3. Choose exactly one primary **cluster**:
   - orchestration, product, engineering, design, security, quality, architecture, growth, continuation
4. Choose:
   - 1 primary skill
   - optional 1 secondary skill only if clearly justified
5. Resolve deprecated aliases using `skill-index.json` (`replaced_by`).
6. Recommend planning model vs execution model for meaningful tasks.
7. Output the routing decision and wait for confirmation if execution scope is non-trivial.

## Output format

```text
Task classification:
  Goal: [one line]
  Deliverable: [what the user wants]
  Risk: [low/medium/high]
  Ambiguity: [clear/partial/unclear]

Cluster decision:
  Primary cluster: [cluster]
  Why: [short reason]

Skill selection:
  Primary: [skill]
  Secondary (optional): [skill or none]
  Alias resolution: [if alias used, show replacement]

Model recommendation:
  Planning: [model]
  Execution: [model]
  Tradeoff: [one line]

Next step:
  [ask for confirmation or proceed plan-first]
```
