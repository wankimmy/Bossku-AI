# Project Understanding Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when the first job is to understand what a project is about before doing deeper work.

## Workflow

1. Read the nearest README, docs, manifests, and top-level structure.
2. Read the whole source code (or systematically through all key areas) and try to understand it—purpose, main workflows, and how the code is organized. Don’t rely only on README; use the code as the primary source of truth.
3. Identify the stack, framework, and likely entry points from both docs and code.
4. Distinguish confirmed facts from inference.
5. Draft `agent-profile.md` using only evidence-supported facts, plus `Inferred:` or `Unknown` markers where needed.
6. Recommend the next expert skills to load.
7. Store durable project understanding in memory for future sessions.
8. If you are not sure about something important, ask the user instead of guessing.

## Output expectation

- project summary
- likely users and purpose
- stack and architecture summary
- important source-of-truth files
- confirmed vs inferred
- agent-profile draft or update
- recommended next skills
- memory update recommendation
