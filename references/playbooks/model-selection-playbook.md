# AI Model Selection Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when recommending which AI model best fits a task.

## Workflow

1. Define the task shape:
   - reasoning depth
   - speed sensitivity
   - cost sensitivity
   - coding/tool use
   - multimodal input
   - long context
2. Identify what failure would be most expensive.
3. Recommend a primary model and one fallback.
4. Explain the tradeoff in capability, latency, cost, and reliability terms.
5. State clearly what assumptions are time-sensitive and should be re-verified.

## Output expectation

- task profile
- recommended model
- fallback model
- tradeoff explanation
- caveats
