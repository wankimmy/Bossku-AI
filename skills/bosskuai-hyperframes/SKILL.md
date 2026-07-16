---
name: bosskuai-hyperframes
description: Use this for Hyperframes video composition work, including HTML-based scene authoring, preview/render workflows, CLI commands, agent-friendly composition updates, and GSAP-assisted animation inside Hyperframes projects.
---

# BosskuAI Hyperframes

Use this for Hyperframes video composition work, including HTML-based scene authoring, preview/render workflows, CLI commands, agent-friendly composition updates, and GSAP-assisted animation inside Hyperframes projects.

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## Working Rules

- Prefer the simplest composition or edit that solves the request.
- Keep changes local to the Hyperframes files, blocks, or components involved.
- Use upstream Hyperframes docs and repo examples as the source of truth when behavior matters.
- Use `/hyperframes` for composition authoring, `/hyperframes-cli` for CLI workflows, and `/gsap` for animation details when those skills are available in the agent environment.
- Keep outputs deterministic and explicit so previews and renders stay reproducible.

## Typical Checks

- Confirm the composition structure matches the requested output.
- Preview before render when the task affects layout, timing, or animation.
- Run the narrowest available render or lint check that proves the change works.
- Call out anything that still depends on runtime assets, fonts, or external media.

## Default Output

- Start with the answer or changed recommendation.
- Use concise bullets for tradeoffs.
- Avoid generic AI/SaaS phrasing.
- For implementation work, include exact files, commands, tests, or review notes.

## Verification

Before finalizing, check:

- Did the output solve the actual request?
- Are assumptions and risks visible?
- Is there a concrete next action?
- Did we avoid loading unnecessary context?
