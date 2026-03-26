# BosskuAI Optional Hooks

These are opt-in, hook-ready reminder scripts for teams that want light automation without unsafe persistence.

## Design goals

- opt-in only
- no automatic memory writes
- no automatic rule edits
- no blocking behavior
- stderr-only reminders
- stdin passthrough so they can fit common hook systems safely

## Available scripts

- `session-start-reminder.sh`
  Use at session start to reinforce plan-first behavior and shared-memory discipline.
- `pre-compact-reminder.sh`
  Use before compaction or context trimming to remind the assistant to preserve a clean handoff.
- `session-end-reminder.sh`
  Use at response stop or session end to remind the assistant to promote durable learnings deliberately, not automatically.

## Example usage

Manual:

```bash
bash ./ai-assistant/hooks/session-start-reminder.sh
bash ./ai-assistant/hooks/pre-compact-reminder.sh
bash ./ai-assistant/hooks/session-end-reminder.sh
```

These scripts also work in hook systems that pass JSON on stdin because they echo stdin back to stdout unchanged and print reminders to stderr.

## Safety notes

- Review any hook integration with `bosskuai-agent-security-hardening`.
- Keep hooks advisory unless you have a clear reason to block behavior.
- Prefer review checkpoints over silent automation.
- If a hook ever starts mutating memory, rules, or skills automatically, treat that as a security-sensitive change and review it explicitly first.
