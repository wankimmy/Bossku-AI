---
name: bosskuai-hyperframes
description: "Use for Hyperframes: authoring, previewing, and rendering HTML compositions (seekable GSAP/CSS animation) to MP4 with the hyperframes CLI. Picking a video tool or AI video generation: video."
---

# BosskuAI Hyperframes

## Fast Path

1. Confirm the requested outcome and constraints.
2. Read the Facts below before touching a composition.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## Working Rules

- Prefer the simplest composition or edit that solves the request.
- Keep changes local to the Hyperframes files, blocks, or components involved.
- Use upstream Hyperframes docs and repo examples as the source of truth when behavior matters.
- If the upstream skills are installed (`npx skills add heygen-com/hyperframes`: `/hyperframes` router, `/hyperframes-cli`, `/hyperframes-animation`, ...), follow them; otherwise use the Facts below.
- Keep outputs deterministic and explicit so previews and renders stay reproducible.

## Facts (github.com/heygen-com/hyperframes, checked 2026-09-26)

- Needs Node.js 22+, FFmpeg, and headless Chrome.
- CLI: `npx hyperframes init <name>`, `npx hyperframes preview` (live reload), `npx hyperframes render` (MP4); `lint`, `check`, `snapshot`, `doctor` also exist.
- A composition is plain HTML: timed elements carry `class="clip"`, `data-start` and `data-duration` (seconds), and `data-track-index` (layer order). Animations must be seekable; register GSAP timelines on `window.__timelines` (e.g. `window.__timelines.launch = tl`).
- The README documents no `render({ frames })` JS API; render through the CLI.

## Typical Checks

- Confirm the composition structure matches the requested output.
- Preview before render when the task affects layout, timing, or animation.
- Run `npx hyperframes lint` or `check`, then `render`, and inspect the MP4.
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
