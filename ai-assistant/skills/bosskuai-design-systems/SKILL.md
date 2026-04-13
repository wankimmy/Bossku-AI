---
name: bosskuai-design-systems
description: Use this for creating, auditing, or applying design systems — including DESIGN.md generation, design token definition, component specification, brand reference calibration, and systematic design language enforcement.
---

# BosskuAI Design Systems

Use this skill for **design systems as a whole** — tokens, component libraries, brand consistency, DESIGN.md artifacts, and the foundational design language that screens and flows are built on.

## How this differs from nearby skills

- **`bosskuai-ui-ux-design-to-code`**: screen-level critique and handoff; this skill handles the system-level foundation that screens are built on. Load both when building UI from scratch.
- **`bosskuai-3d-web-development`** / **`bosskuai-gsap-animation`** / **`bosskuai-lenis-smooth-scroll`**: specific implementation technologies; this skill provides the design language they must follow.
- **`bosskuai-i18n-l10n`**: localization readiness; this skill defines the visual system that must accommodate expansion and RTL.

## Mindset

- A design system is a contract, not a gallery — tokens, components, and rules must be specific enough to generate code from.
- Start from brand DNA (personality, values, audience), not from colors.
- Reference real brand systems (via `getdesign.md/{company}/design-md`) to calibrate quality and structure, never to copy values.
- Tokens are the single source of truth — every visual decision should trace back to a named token.
- Accessibility and responsive behavior are part of the system, not afterthoughts.
- Anti-generic-AI aesthetics: reject predictable gradients, stock palettes, and cookie-cutter spacing. Distinctive brands have intentional, opinionated design choices.

## The DESIGN.md format (Google Stitch 9-section)

Every DESIGN.md follows this structure. Use it for generation and auditing:

| # | Section | What it captures |
|---|---------|-----------------|
| 1 | Visual Theme & Atmosphere | Mood, density, design philosophy, core aesthetic principles |
| 2 | Color Palette & Roles | Semantic naming, hex codes, functional roles, token systems |
| 3 | Typography Rules | Font families, complete hierarchy table with size/weight/spacing |
| 4 | Component Stylings | Buttons, cards, inputs, navigation with all states (hover, focus, active, disabled) |
| 5 | Layout Principles | Spacing systems, grids, whitespace philosophy, border-radius scales |
| 6 | Depth & Elevation | Shadow systems, surface hierarchy, depth tokens |
| 7 | Do's and Don'ts | Design guardrails and anti-patterns specific to the brand |
| 8 | Responsive Behavior | Breakpoints, touch targets, responsive collapsing strategies |
| 9 | Agent Prompt Guide | Quick color references and ready-to-use AI prompts for building on-brand components |

## Workflow

1. **Brand discovery** — Identify brand personality (3+ adjectives), target audience, competitive positioning, and existing visual assets. If an existing design system or style guide exists, audit it rather than replacing it.

2. **Reference calibration** — Select 2-3 reference brands from `getdesign.md` that match the target personality axis (e.g., playful-serious, luxurious-accessible, bold-subtle). Fetch their DESIGN.md for structural patterns and quality benchmarking. Extract how they define tokens, component depth, and responsive approaches — not their actual values.

3. **Token definition** — Define the complete token system:
   - **Color**: primitive palette (5-8 base colors with hex) + semantic roles (primary, secondary, accent, success, warning, error, info) + background/surface/text roles + dark mode mapping if applicable
   - **Typography**: font families (display, body, mono) + full hierarchy table (h1-h6, body, caption, overline with size, weight, line-height, letter-spacing) + responsive scaling
   - **Spacing**: base unit (4px or 8px) + complete scale + content max-width
   - **Border-radius**: system with named tokens (none, sm, md, lg, full)
   - **Shadows/elevation**: sm, md, lg, xl shadow tokens + z-index scale + overlay opacity

4. **Component specification** — Define core components with all states:
   - Buttons: primary, secondary, ghost, destructive — default, hover, active, focus, disabled, loading
   - Cards: variants, padding, border, shadow
   - Inputs: text, select, checkbox, radio — default, focus, error, disabled, filled
   - Navigation: header, sidebar, mobile nav patterns
   - Feedback: toast, banner, tooltip, badge

5. **Layout and responsive** — Grid system (columns, gutters, margins per breakpoint), spacing rhythm, breakpoints with component behavior changes, touch targets (44x44px minimum).

6. **Design rules** — 5-8 brand-specific do's and don'ts with concrete examples. Include anti-patterns that break brand consistency.

7. **Agent prompt guide** — System prompt for AI tools to generate on-brand UI. Component generation templates. Design review prompt templates.

8. **DESIGN.md assembly** — Compile into the 9-section format. Verify every token has a semantic role, every component has all states, and the agent prompt guide is actionable.

9. **Pencil integration** (optional, when .pen files exist) — Use `get_variables` to read existing design tokens from .pen files. Use `set_variables` to apply new tokens. Use `batch_design` to create component specimens. Use `get_screenshot` to validate visual output against the DESIGN.md.

## Guardrails

- Do not copy brand systems from getdesign.md references — use them for structural calibration only.
- Do not define tokens without semantic roles — bare hex codes are not a system.
- Do not skip component states — every interactive element needs all state definitions.
- Do not generate a DESIGN.md without confirming brand inputs with the user first.
- If the project already has a design system, audit and enhance it rather than replacing it.
- Verify all text-background color pairs pass WCAG AA contrast (4.5:1 body text, 3:1 large text).
- When fetching reference DESIGN.md files, use WebFetch to retrieve from `getdesign.md/{company}/design-md`. If offline or unavailable, describe the 9-section structure and work from user-provided brand inputs.

## Output format

- **Generation mode**: Complete DESIGN.md in the 9-section Google Stitch format, ready to drop into a project root.
- **Audit mode**: Gap analysis report with severity-tagged findings (missing tokens, incomplete states, contrast failures, absent responsive rules) and specific remediation steps.

## References

- `../../references/playbooks/design-systems-playbook.md`
- `../../references/checklists/design-systems-checklist.md`
- `../bosskuai-ui-ux-design-to-code/SKILL.md` (screen-level application of the system)
