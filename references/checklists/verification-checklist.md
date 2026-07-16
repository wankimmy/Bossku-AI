# Verification Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Did you identify the relevant verification steps for this repo instead of using a generic checklist?
- Did you run build or compile checks if the project provides them?
- Did you run type checks, lint, tests, coverage, or security checks where they are relevant and available?
- Did you inspect the diff or changed files for unintended changes?
- Did you check for leftover debug logs, temporary scaffolding, or obvious secret leakage in changed code?
- Did you separate confirmed verification from assumptions or skipped checks?
- If verification could not be completed, did you say exactly what was blocked and why it matters?
- Did you state whether the current result is ready for handoff, needs follow-up, or still carries meaningful risk?
