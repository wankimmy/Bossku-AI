# Bug Patterns

## Recurring issue

- Pattern: duplicated instructions across multiple entry files cause drift and unnecessary prompt weight
- Safeguard: keep one canonical contract and let tool-specific files delegate
