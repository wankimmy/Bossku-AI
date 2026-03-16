# Example Output: Codebase Analysis

This is an example of the style the agent should produce when reading source code.

## Architecture summary

The application uses a modular backend with feature-based folders and a thin controller layer. The real business flow is:

1. request enters controller
2. request data is validated
3. use case or service handles orchestration
4. repository or ORM layer persists changes
5. side effects are triggered through jobs or events

## Findings

1. The documented architecture and the actual code are mostly aligned, but one feature bypasses the intended service layer and writes directly from the controller.
2. Audit logging exists, but it is not consistently tied to the same transaction as the business write path.
3. Tests cover the happy path but do not cover retries, duplicates, or self-approval denial paths.

## Risks

- business rule drift if more controller-level writes are added
- partial writes if side effects remain outside transaction boundaries
- false confidence from shallow feature coverage

## Confidence

- Confirmed from source code: high
- Confirmed from tests: moderate
- Inferred from surrounding patterns: moderate
