---
name: bosskuai-malaysia-pdpa-privacy
description: Use this for Malaysia PDPA-aware privacy review, data minimization, consent, retention, user rights, vendor processors, and privacy-safe SaaS operations.
---

# BosskuAI Malaysia PDPA Privacy

Use this skill for **product and engineering privacy posture** on Malaysian personal data. This is product guidance, not legal advice; a lawyer signs off on obligations.

## How this differs from nearby skills

- **`bosskuai-legal-compliance`**: broader compliance and policy alignment; this skill is PDPA-specific and implementation-facing.
- **`bosskuai-cybersecurity-risk`**: protects data from attackers; this skill governs what is collected and kept in the first place.
- **`bosskuai-data-architecture`**: designs storage and pipelines; this skill constrains what may flow through them.

## The PDPA principles, as engineering requirements

Malaysia's PDPA is built on principles that map to concrete product decisions:

- **General/consent**: processing needs a lawful basis and a clear notice. Consent must be recorded, not assumed.
- **Notice and choice**: tell users what is collected, why, who receives it, and how to reach you, in both English and Bahasa Malaysia where users expect it.
- **Disclosure**: do not share beyond the stated purpose without consent.
- **Security**: protect against loss, misuse, and unauthorized access.
- **Retention**: do not keep personal data longer than the purpose requires.
- **Data integrity**: keep it accurate and current.
- **Access**: users can access and correct their data.

Note that the PDPA has been amended in recent years, including changes around breach notification and data protection officers. Verify current obligations against the official source rather than relying on this summary.

## Design decisions this drives

- **Minimize at the form**: the cheapest privacy control is not collecting the field. Challenge every optional field, especially IC number, full address, and date of birth.
- **Sensitive data**: health, religion, political opinion, and similar categories carry stricter conditions. Avoid storing them unless the product genuinely requires it.
- **Retention schedule**: define per data type, with an actual deletion job, not an intention.
- **Access, correction, export, deletion**: build the workflow before scale makes it manual and painful.
- **Processors and vendors**: every third party receiving personal data (analytics, support, email, AI APIs) needs a purpose and an agreement. Sending customer records to an external model API is a disclosure.
- **Cross-border transfer**: know where data physically lands, including your hosting region and each vendor's.
- **Breach readiness**: know in advance who assesses, who notifies, and within what window.

## Guardrails

- Do not present this as legal advice or claim compliance certainty. Flag where counsel is needed.
- Do not log personal data into application logs, error trackers, or analytics events.
- Do not use production personal data in development, testing, or model prompts.
- Deletion means deletion, including backups policy, search indexes, caches, and vendor copies.
- Anonymization must actually prevent re-identification; a hashed IC number is still identifying.

## Output format

```text
Personal data inventory:
  [field] - [purpose] - [lawful basis] - [retention] - [where stored]

Sensitive data: [any, and justification]
Vendors/processors: [vendor - data shared - region]

Findings:
  P0/P1/P2 - [issue] - [principle affected] - [fix]

User rights workflow: [access / correct / export / delete - built or missing]
Retention enforcement: [job or process that actually deletes]
Needs legal review: [items]
```

## References

- `../../references/checklists/malaysia-pdpa-privacy-checklist.md`
