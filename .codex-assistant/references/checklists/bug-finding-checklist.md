# Bug Finding Checklist

- What is the exact entry point and expected behavior?
- What changed recently or looks suspicious?
- What happens on empty, null, partial, duplicate, and retry paths?
- Are auth, permission, tenant, or role assumptions being violated?
- Are there race conditions, transaction gaps, or stale-state issues?
- Does the frontend/backend or caller/callee contract drift anywhere?
- What tests should exist but do not?
