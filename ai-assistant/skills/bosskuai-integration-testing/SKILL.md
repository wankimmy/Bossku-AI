---
name: bosskuai-integration-testing
description: Use this for integration test design, contract testing (CDC/Pact), test double strategy, fixture management, and validating behavior at module and service boundaries.
---

# BosskuAI Integration Testing

Use this skill when the question is **how to test the seams between components** — the layer between pure unit tests and full end-to-end flows.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: shapes the full delivery workflow including the test pyramid; this skill goes deep on the integration layer specifically — contract design, test doubles, and fixture management.
- **`bosskuai-coding-best-practices`**: covers general testability at implementation level; this skill covers the architecture of test doubles, contracts, and fixture strategies.
- **`bosskuai-api-design`**: designs external contracts; this skill validates that those contracts are correctly implemented and don't drift between providers and consumers.
- **`bosskuai-bug-finding`**: traces defects in running code; this skill prevents the class of defects that arise from integration assumption mismatch.

## Mindset

- Integration bugs are born from assumption mismatch, not missing logic — they live at the seams, not in the code.
- A test double that diverges from the real dependency is worse than no test — it creates false confidence.
- Contract tests are the cheapest form of integration assurance for independently deployed services.
- Fixture management is a first-class maintenance concern: stale fixtures create test debt silently.

## Integration test taxonomy

| Layer | What it tests | Scope |
|-------|--------------|-------|
| **Module integration** | Two or more internal modules wired together against real business logic | In-process, no external services |
| **Service integration** | Application calling a real dependency (DB, cache, queue) | Out-of-process; uses test containers or real dev instances |
| **Contract test (consumer)** | Consumer's expectations of a provider's API or event shape | Offline; verified against a recorded contract |
| **Contract test (provider)** | Provider verifies it satisfies all registered consumer contracts | CI gate before deployment |
| **API integration** | Real HTTP call to a deployed service (staging or sandbox) | Out-of-process; slow; run in pre-deploy gate |

## Test double taxonomy

Apply the right double for the situation:

| Double type | When to use | When NOT to use |
|-------------|-------------|----------------|
| **Stub** | Return controlled values for read paths; no assertions | When call count or argument correctness matters |
| **Mock** | Assert that a call was made with specific arguments | For pure read paths where interactions don't matter |
| **Fake** | In-memory implementation of a real dependency (e.g. in-memory queue) | When the fake's behavior diverges materially from the real thing |
| **Spy** | Wrap a real object to observe calls without changing behavior | When full replacement would hide integration bugs |
| **Test container** | Real Docker instance of a DB, queue, or cache | When behavior fidelity is critical and speed is acceptable |

## Contract testing workflow (CDC / Pact pattern)

1. **Consumer defines expectations** — The consumer (caller) writes tests that record what shape they expect from the provider. These produce a "pact" or contract artifact.
2. **Contract is published** — The contract is stored in a contract broker (e.g. Pact Broker, or a repo artifact).
3. **Provider verifies** — The provider runs a verification suite against the published contract in CI. This is the gate: providers cannot deploy if they break a registered consumer contract.
4. **Bidirectional when needed** — For REST: use Pact or Schemathesis. For events/async: capture event schema, consumer reads against schema snapshot.

## Workflow

1. **Map the integration boundaries** — Identify every seam: module-to-module, service-to-service, service-to-DB, service-to-queue, service-to-external API. Each seam is a contract that needs test coverage.

2. **Classify each seam** using the taxonomy above: module integration, service integration, or contract test.

3. **Choose the right double strategy** — For each external dependency in integration tests: select stub, fake, mock, spy, or test container based on how much behavior fidelity is needed.

4. **Define the contract** — For each seam, state explicitly:
   - Request/event shape (required fields, types, nullable semantics)
   - Response/event shape and error contract
   - Side effects (idempotency, ordering guarantees)

5. **Design the fixture strategy**:
   - What seed data does each test need?
   - How is it reset between tests? (per-test truncation, transaction rollback, fixture reload)
   - Who owns the fixture data definition? (code, migration file, factory)
   - How is fixture drift detected? (schema change breaks factory — treat this as a signal)

6. **Set the scope gate for each test** — State explicitly: what is real vs doubled. A test where "everything is mocked" is not an integration test.

7. **Run in the right environment gate**:
   - Module integration: run in every PR
   - Service integration (test containers): run in CI on merge
   - Contract tests: run as a CI gate blocking deployment, not just a PR check
   - API integration against real service: run in staging gate, not PR

8. **Monitor contract drift** — After deployment, if a provider's actual behavior diverges from the contract, the contract broker should alert. If not using a contract broker, add a schema snapshot test.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not use mocks to replace every dependency in an "integration" test — that is a unit test wearing a costume.
- Do not let fixture data accumulate without a reset strategy — stale fixtures create order-dependent test suites.
- Do not conflate consumer-driven contract tests with provider-written API tests — these are different things with different owners.
- Do not add test containers for every test — they are slow; use them only where behavior fidelity materially changes the test's value.

## Output format

```text
Integration seam map:
  [seam] — [type: module / service / contract / api] — [current coverage: yes/no/partial]

Test double strategy:
  [dependency] — [double type chosen] — [rationale]

Contract definitions:
  [seam] — [request shape] — [response/event shape] — [error contract]

Fixture strategy:
  [test scope] — [seed approach] — [reset mechanism] — [owner]

Environment gate:
  [test type] — [when it runs in CI]

Gaps identified:
  [seam with no coverage] — [recommended test type] — [effort estimate: low/medium/high]
```

## References

- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/api-design-checklist.md`
