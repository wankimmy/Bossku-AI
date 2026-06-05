# Playbook: Code audit

**Goal:** full repository audits cover **functionality → design & best practices → performance → tests**, then a dedicated **security** pass. Security-only prompts skip the full dimension set.

**Routing:** `audit_mode=full` on repo audit prompts (Bossku `RepoTaskDetector` + `PromptRouteClassifier`).

**Skills**

- `bosskuai-rigorous-code-review`
- Add domain specialist from [`../skills/`](../skills/) as needed

**Deep playbook:** [`../ai-assistant/references/playbooks/bosskuai-rigorous-code-review-playbook.md`](../ai-assistant/references/playbooks/bosskuai-rigorous-code-review-playbook.md)

**Output contract:** [`../agents/auditor.md`](../agents/auditor.md) (adapt severity vs Pass/Fail).

**Patterns for multi-surface tools:** [`../docs/multi-agent-architecture.md`](../docs/multi-agent-architecture.md)
