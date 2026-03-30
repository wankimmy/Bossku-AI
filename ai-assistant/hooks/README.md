# BosskuAI Optional Hooks

Opt-in reminder scripts — no automatic memory writes, no rule edits, no blocking behavior. Reminders go to stderr; stdin is passed through unchanged.

## Scripts

- `session-start-reminder.sh` — reinforce plan-first and shared-memory discipline at session start
- `pre-compact-reminder.sh` — remind to preserve a clean handoff before compaction
- `session-end-reminder.sh` — remind to promote durable learnings at session end

## Usage

```bash
bash ./ai-assistant/hooks/session-start-reminder.sh
bash ./ai-assistant/hooks/pre-compact-reminder.sh
bash ./ai-assistant/hooks/session-end-reminder.sh
```

For post-session hygiene:

```bash
bash ./ai-assistant/scripts/learning-doctor.sh
```

## Safety

- Review hook integrations with `bosskuai-agent-security-hardening`.
- Keep hooks advisory. If a hook starts mutating memory, rules, or skills automatically, treat it as a security-sensitive change and review explicitly.
