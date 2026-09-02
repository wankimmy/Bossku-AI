---
name: bosskuai-handoff
description: "Use when the user asks to hand off, wrap up for another agent or session, or write a continuation document — compacts the conversation into .bossku/memory/handoff.md so a fresh agent in any tool can continue. Context-pressure handoffs belong to bosskuai-context-limit-continuation."
argument-hint: "What will the next session be used for?"
---

# BosskuAI Handoff

Write a handoff document summarising the current conversation so a fresh agent (any tool, any model) can continue the work without the chat history.

## Where it goes

- Save to `.bossku/memory/handoff.md` in the project (create `.bossku/memory/` if missing). It is the file every BosskuAI session reads first and it is never exported to Obsidian; add `.bossku/` to `.gitignore` if the project does not already ignore it (`bossku init` does not).
- Overwrite rather than append; the file holds only the *current* unfinished work. Tell the user to clear it when the work is done.
- If the project has no `.bossku/` and the user does not want one, write to the OS temp directory and print the path.

## Contents (follow `references/session-handoff-template.md`)

1. Goal and current status: done, not done, blocked.
2. Key decisions already made, with the reason, so the next agent does not reopen them.
3. Files and artifacts touched; link PRDs, plans, ADRs, issues, commits, and diffs by path or URL instead of copying them.
4. Verification performed and checks *not* performed.
5. Open risks, unknowns, and follow-ups.
6. Suggested skills the next agent should invoke, by id.
7. A `FOR_NEXT_MODEL` paste block (ordered next steps with paths or commands).

If the user passed arguments, treat them as what the next session will focus on and tailor the doc accordingly.

## Guardrails

- Redact API keys, passwords, tokens, and personal data by hand; the handoff is ephemeral and does not go through `bossku remember`.
- Do not duplicate content already captured elsewhere; reference it.
- Durable lessons discovered while writing the handoff go through `bosskuai-continuous-learning`, not into `handoff.md`.
- When the handoff is forced by context or token pressure, switch to `bosskuai-context-limit-continuation` (same file, plus model recommendation and stop discipline).

## References

- `../../references/session-handoff-template.md`
- `../../references/memory-first-handoff-protocol.md`
