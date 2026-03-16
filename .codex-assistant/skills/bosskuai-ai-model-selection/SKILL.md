---
name: bosskuai-ai-model-selection
description: Use this for recommending which AI model is suitable for a task based on reasoning depth, speed, tool use, coding needs, multimodality, cost sensitivity, and reliability tradeoffs.
---

# BosskuAI AI Model Selection

Use this skill when the user wants to know which AI model fits a specific task.

## Workflow

1. Classify the task requirements:
   - reasoning depth
   - speed
   - cost
   - coding/tool use
   - multimodal input
   - long context
2. Identify the most important failure mode if the wrong model is chosen.
3. Recommend a primary model profile and fallback profile.
4. Explain the tradeoff in capability, latency, cost, and reliability terms.
5. Make clear which claims are time-sensitive and should be re-verified.

## Output expectation

- task profile
- primary recommendation
- fallback recommendation
- tradeoff explanation
- caveats

## References

- `../../references/playbooks/model-selection-playbook.md`
- `../../references/checklists/model-selection-checklist.md`
