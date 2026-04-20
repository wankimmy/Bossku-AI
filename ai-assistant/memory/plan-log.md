# Plan Log

Use this file for compact pre-execution plans that should survive across chats, tools, or longer workstreams.

Write here only when the task is non-trivial **and** the plan is likely to matter later:

- multi-step or multi-file execution
- architecture or product decisions
- likely handoff or continuation
- user explicitly asks for a plan first

Do **not** dump raw chain-of-thought or speculative brainstorming here. Keep each entry compact, actionable, and easy for another model to reuse.

## Entry template

```markdown
## YYYY-MM-DD — [Cursor | Codex | Claude | other] — [Short title]

- **User goal:** one line
- **Why this plan was stored:** architecture / multi-step / likely handoff / explicit planning request / other
- **Planned approach:** 3-6 short bullets
- **Expected files or surfaces:** paths, services, docs, or tools
- **Open assumptions / risks:**
- **Status:** planned / in-progress / superseded / completed
- **Follow-up note:** link or pointer to `learning-log.md` entry when execution finishes
```

## Promotion guidance

- If the plan becomes a durable workflow used repeatedly, promote it into a playbook, checklist, skill, or rule.
- If the task finishes, keep the plan entry concise and record outcomes in `learning-log.md`.
