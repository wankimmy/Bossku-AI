# Context-Limit Continuation Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is the task at risk of exceeding the current model's context or reply budget (or usage/quota)?
- Can you stop now before the response becomes truncated or low quality?
- Have you summarized what is already done and what remains?
- Have you listed the key files, decisions, and open risks?
- Have you updated `ai-assistant/memory/active-continuation.md` (or documented why not)?
- Have you recommended primary and fallback models for the **remaining** work (`bosskuai-ai-model-selection`)?
- Have you told the user to open a **new chat/session** with the recommended model and paste the continuation block?
- Have you given the user a clear retry or continue instruction?
- Is the continuation state compact enough to fit safely into the next prompt?
