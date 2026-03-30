# Pitfalls

Use this folder for recurring traps, failure modes, and warnings that should be surfaced before similar work is done again.

## Files

- [general-known-pitfalls.md](general-known-pitfalls.md) — index and cross-cutting notes
- [security-pitfalls.md](security-pitfalls.md)
- [performance-pitfalls.md](performance-pitfalls.md)
- [business-logic-pitfalls.md](business-logic-pitfalls.md)
- [product-pitfalls.md](product-pitfalls.md)
- [ai-workspace-pitfalls.md](ai-workspace-pitfalls.md)

## When to promote a lesson here

- It has caused defects or wasted effort more than once
- Future tasks should be explicitly warned about it
- It is not yet worth a full skill or playbook update

## Integrity check

To verify that every `../../references/...` link from skills resolves to a real file, run from the BosskuAI repo root:

```bash
./scripts/verify-skill-references.sh
```
