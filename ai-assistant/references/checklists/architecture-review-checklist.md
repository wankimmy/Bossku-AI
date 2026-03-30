# Architecture Review Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What are the core modules, layers, and boundaries?
- Is the responsibility placed in the right layer?
- Which abstractions are stable versus accidental?
- What coupling, scalability, or operability risks exist?
- What future changes become harder if this path is chosen?
- What should be a local fix versus a structural redesign?
