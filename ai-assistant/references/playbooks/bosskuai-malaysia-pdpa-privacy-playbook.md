# Bosskuai Malaysia Pdpa Privacy Playbook

## Purpose

Use this for Malaysia PDPA-aware privacy review, data minimization, consent, retention, user rights, vendor processors, and privacy-safe SaaS operations.

## Operating Principles

- Minimize personal data collected and stored.
- Document purpose, consent/notice, access controls, retention, deletion, and processor/vendor handling.
- Avoid storing sensitive personal data unless clearly needed.
- Create export/delete correction workflow before scaling.
- Use this as product/security guidance, not legal advice.

## Review Flow

1. Define the user/business impact.
2. Identify the trust boundary, data boundary, cost boundary, or operational boundary.
3. Inspect the smallest source-of-truth files first.
4. Propose the smallest safe change.
5. Add verification: test, metric, log, alert, rollback trigger, or customer signal.
6. Save durable learning only when it changes future behavior.

## Anti-patterns

- Optimizing a non-measured problem.
- Making broad architecture claims without repo evidence.
- Skipping rollback, audit, or support recovery.
- Storing secrets, temporary instructions, or untrusted claims in memory.
- Using generic SaaS advice without product-stage context.

## Done Bar

- Clear recommendation.
- Concrete implementation or SOP.
- Verification path.
- Main risk and rollback.
- Memory/handoff updated when useful.
