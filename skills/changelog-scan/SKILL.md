---
name: changelog-scan
description: >
  Use when gathering recent merges, PRs, and commits for release-note content.
  Triggers: changelog scan, what shipped, release prep, changelog-drafter loop.
user_invocable: true
---

# Changelog Scan (Claude)

Same contract as the Grok version. Produce the per-item blocks + Scan Summary.

Key rules: cite PR numbers, surface breaking/security explicitly, ignore pure dep and bot noise unless security.