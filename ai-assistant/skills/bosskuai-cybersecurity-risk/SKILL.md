---
name: bosskuai-cybersecurity-risk
description: Use this for cybersecurity review, privacy, abuse-case analysis, auth and authorization concerns, trust boundaries, fraud risk, and operational risk evaluation.
---

# BosskuAI Cybersecurity and Risk

Use this skill when the task involves security, privacy, abuse, or operational risk.

## Workflow

1. Identify sensitive assets and trust boundaries.
2. Trace who can read, write, approve, override, or exploit the flow.
3. Look for data leaks, privilege escalation, fraud, spam, abuse, compliance risks, and unsafe defaults.
4. Explicitly check secrets handling, input validation, auth and authorization, injection paths, privacy exposure, and rate-limiting or abuse controls when they are relevant.
5. Review auditability, alerting, and recovery implications.
6. Separate confirmed issues from inferred risks.

## Output expectation

- threat summary
- concrete risks
- severity and impact
- missing controls
- recommended mitigations

## References

- `../../references/playbooks/security-review-playbook.md`
- `../../references/checklists/security-risk-checklist.md`
- `../../references/checklists/agent-security-hardening-checklist.md`
