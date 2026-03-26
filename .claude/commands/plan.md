# BosskuAI Plan Command

Use this command for meaningful tasks that should be planned before implementation or major recommendations.

## Intent

- classify the task
- recommend the best-fit model for the work
- restate requirements clearly
- surface risks and unknowns
- produce a small, verifiable plan
- wait for user confirmation before code changes

## Instructions

1. Start in planning mode, not execution mode.
2. State the recommended model for the task and the tradeoff briefly.
3. Classify the task using the BosskuAI skill roster and load only the minimum relevant skills.
4. Read nearby repo evidence before planning if the task depends on project context.
5. Restate the goal, assumptions, and constraints.
6. Produce a phased or step-by-step plan with dependencies, risks, testing strategy, and success criteria.
7. End by explicitly waiting for confirmation before implementation or irreversible changes.

## Output

- task classification
- recommended model and why
- skills to load
- requirements restatement
- plan
- risks and unknowns
- verification strategy
- waiting for confirmation
