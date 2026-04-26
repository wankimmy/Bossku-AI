# Token Saver Checklist

Use this when reducing prompt, rule, or response length.

## Preserve

- Meaning and task boundaries.
- File paths, commands, versions, and exact names.
- Safety warnings and irreversible action checks.
- Verification status.
- Known limitations.

## Remove

- Repeated intent statements.
- Long philosophy that does not change behavior.
- Generic encouragement.
- Duplicate examples.
- Specialist rules that can live in playbooks.
- Protocol chatter visible to the user.

## Compression Pattern

Before:

> Explain the goal, then repeat the rule, then give several examples.

After:

> Rule: do X when Y. Examples live in `references/...`.

## Done When

- The shortened version still tells the model when to act.
- It still says what not to do.
- It links deeper references instead of copying them.
- Validation still passes.
