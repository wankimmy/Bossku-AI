# Tech Lead Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Is there a one-paragraph problem statement with a success metric before solutioning?
- Was the process weight chosen deliberately (ticket, RFC, council) and the RFC given a comment window?
- Is the first slice a walking skeleton, with later slices vertical, flag-guarded, and independently deployable?
- Are migrations and API changes expand/contract so slices ship without coordination?
- Did the people doing the work estimate it, with spikes for unknowns and no false precision?
- Are WIP limits and a daily blocker surface in place?
- Is the definition of done explicit: tests, docs/ADR, migration and rollback plan, observability, flag cleanup date, review, staging verification, acceptance?
- Are PRs small (under ~400 lines), templated, owned via CODEOWNERS, and reviewed within 24 hours?
- Are automated gates (lint, typecheck, tests, coverage on changed lines, secret and dependency scans) required, and is a red main the top priority?
- Are structural decisions recorded as ADRs?
- Is the debt ledger reviewed monthly with 10–20% capacity reserved and every item owned?
- Do on-call, runbooks, incident roles, and blameless postmortems with tracked actions exist?
- Are DORA metrics and cycle-time breakdown tracked with targets, and is the biggest wait the next fix?
- Is bus factor ≥ 2 for every critical area, with 1:1s and growth plans in place?
- Was the retro run with 1–3 owned actions, and the durable lesson saved with `bossku remember`?
