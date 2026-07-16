# bosskuai-ui-ux-design-to-code Full Playbook

Original detailed operating notes moved out of SKILL.md to reduce prompt bloat.

---

---
name: bosskuai-ui-ux-design-to-code
description: Use this for UI/UX review, interface critique, design systems thinking, mobile-responsive behavior, accessibility (WCAG), and translating designs or screenshots into implementation-ready code guidance.
---

# BosskuAI UI/UX and Design-to-Code

Use this skill for **screens, flows, and implementation-ready UI guidance** — from critique of a design to handoff for engineering.

## How this differs from nearby skills

- **`bosskuai-design-systems`**: handles the system-level foundation (tokens, DESIGN.md, component specs); load that skill when creating or auditing a design system. This skill uses the design system as input for screen-level work.
- **`bosskuai-3d-web-development`**: immersive 3D WebGL experiences; load instead of this skill when the work is Three.js/R3F/Spline.
- **`bosskuai-engineering-delivery`**: the full implementation workflow; this skill handles the UI design-to-code translation step within that workflow.
- **`bosskuai-coding-best-practices`**: general code quality; this skill handles the design, UX, and component-pattern decisions specifically.

## Mindset

- Every screen has a job — identify it before critiquing the design.
- Mobile-first: design and review for the smallest screen first, then enhance.
- States are as important as the happy path — loading, empty, error, and partial-data states all need design decisions.
- Accessibility is not optional — it is part of implementation correctness.
- Anti-generic-AI aesthetics: if it looks like every other AI-generated landing page, redesign it. Reject generic system fonts, predictable purple gradients, and cookie-cutter layouts.
- Distinctive design: intentional typography pairings, orchestrated motion, asymmetric spatial composition, and visual depth through gradients, textures, and layered effects.


## Anti-AI UI Gate

Before accepting a UI direction, run `../../references/checklists/anti-ai-ui-checklist.md`.

Reject by default:

- purple/blue gradient hero with glowing blobs
- glassmorphism cards everywhere
- generic three-card feature sections
- fake dashboard mockups used as decoration
- robot/sparkle/wand/neural-network icons as the main visual idea
- over-empty SaaS spacing with weak information architecture
- repeated rounded-card rhythm across every section

Prefer product-specific visual evidence: screenshots, CLI output, file trees, diffs, logs, real states, domain-specific controls, and typography/layout choices that match the product.

## UX review lenses (Nielsen's heuristics — apply selectively)

1. **Visibility of system status** — Is the user always informed of what is happening? (loading states, success/error feedback)
2. **Match between system and real world** — Are labels, metaphors, and flows familiar to the user's mental model?
3. **User control and freedom** — Can users undo, go back, or escape from any flow?
4. **Consistency and standards** — Are patterns consistent across the product? Does the design follow platform conventions?
5. **Error prevention** — Does the design prevent errors before they happen? (confirmation dialogs, disabled states, field constraints)
6. **Recognition over recall** — Are options visible, not buried? Does the user need to memorize state between screens?
7. **Flexibility and efficiency** — Are there shortcuts for power users without cluttering the experience for new users?
8. **Aesthetic and minimalist design** — Is every element earning its space?
9. **Help users recognize, diagnose, and recover from errors** — Are error messages specific, in plain language, and actionable?
10. **Help and documentation** — Are complex flows supported by in-context help or guidance?

## Workflow

1. **Identify the user's goal on this screen** — Not the product's goal, the user's goal. What are they trying to accomplish and what is the success state?

1b. **Check for a project DESIGN.md** — If one exists in the project root, load it and use its tokens, components, and rules as the baseline for all critique and handoff. If none exists, note this gap and recommend creating one via `bosskuai-design-systems`.

2. **Break the interface into a component hierarchy**:
   - Layout containers (page, section, card, modal)
   - Navigation and wayfinding
   - Content components (text, image, table, list)
   - Action components (buttons, forms, inputs, selects)
   - Feedback components (toasts, banners, tooltips, badges)

3. **Map all states for each key component**:
   - Empty: no data yet
   - Loading: async operation in progress
   - Error: operation failed
   - Success / populated: normal state
   - Disabled / locked: unavailable action
   - Edge cases: very long text, very large numbers, zero results, maximum items

4. **Check responsive behavior** (mobile-first):
   - Layout at 320px, 375px (mobile), 768px (tablet), 1024px, 1440px (desktop)
   - Navigation: does it collapse, drawer, or stack?
   - Tables/grids: do they scroll, reflow, or collapse?
   - Touch targets: minimum 44×44px tap area
   - Text: minimum 16px body, legible line-height

5. **Check accessibility (WCAG 2.1 AA minimum)**:
   - Color contrast: 4.5:1 for body text, 3:1 for large text and UI components
   - Keyboard navigation: all interactive elements reachable and operable by keyboard alone
   - Focus management: visible focus ring, logical focus order, focus trapped in modals
   - Screen reader: semantic HTML (headings, landmarks, form labels), meaningful alt text, ARIA only where native semantics insufficient
   - Motion: `prefers-reduced-motion` honored for animations
   - Forms: all inputs have visible labels, errors are associated with fields via `aria-describedby`

6. **Translate to implementation primitives**:
   - Layout structure (flex, grid, absolute — which and why)
   - Component list with props and variants
   - State machine for complex interactions
   - Data requirements per component (what API shape is needed?)
   - Interaction rules (hover, focus, active, disabled behaviors)
   - Animation notes (entrance, exit, transition — duration, easing, trigger)

7. **Design critique** — Evaluate mockups for usability issues, design system consistency, and visual distinctiveness. Flag generic or derivative designs.

8. **UX writing** — Craft microcopy for UI elements: button labels, error messages, empty states, tooltips, confirmation dialogs, and onboarding copy. Voice and tone should match the product personality.

9. **Accessibility audit** — Beyond the WCAG checks in step 5, run a structured audit: color contrast, keyboard navigation, screen reader flow, focus management, motion preferences, and form labeling. Use WCAG 2.1 AA as the minimum bar.

10. **Call out ambiguity** — Do not invent silent design decisions. Flag: "This mockup doesn't show the empty state — define it before building."

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not skip accessibility — it is part of implementation correctness, not a nice-to-have.
- Do not design only the happy path — states are part of design.
- Do not accept designs that do not define mobile behavior — ask for it.
- Do not use ARIA to paper over semantic HTML problems — fix the HTML first.

## Output format

```
Screen goal: [user's goal + success state]
Component hierarchy: [layout → containers → components]
State inventory:
  [component] — [empty / loading / error / success / edge states]
Responsive notes:
  Mobile: [behavior / breakpoints]
  Tablet: [behavior]
  Desktop: [behavior]
Accessibility findings:
  [issue] — [WCAG criterion] — [fix]
Implementation handoff:
  Layout: [structure approach]
  Components: [list with props/variants]
  Data needs: [shape per component]
  Interactions: [rules]
  Animations: [notes]
Ambiguities to resolve: [list]
```

## References

- `../../references/playbooks/ui-delivery-playbook.md`
- `../../references/checklists/ui-fidelity-checklist.md`
- `../../references/checklists/anti-ai-ui-checklist.md`

## Anti-AI UI/UX audit matrix

### Humanized interface principles

- Start from user task and emotional state, not component decoration.
- Use visual hierarchy: primary action, secondary action, supporting detail, then metadata.
- Make empty, loading, error, and success states designed, not afterthoughts.
- Use spacing, typography scale, and content rhythm consistently.
- Avoid generic gradient hero sections, identical card grids, overused glassmorphism, and meaningless AI illustrations.

### Product UX checks

- Every screen has one dominant job.
- CTA label explains the action, not vague “Submit” or “Learn More”.
- Forms show required fields, validation timing, error recovery, and success confirmation.
- Tables support filtering, sorting, search, pagination, bulk actions, and empty states when relevant.
- Mobile layout is not just desktop squeezed smaller.

### Accessibility and responsiveness

- Keyboard reachable controls.
- Visible focus states.
- Color contrast sufficient for text and critical UI.
- Tap targets large enough on mobile.
- Motion respects reduced-motion preference.
- Content readable without relying only on color.

### Design-to-code verification

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
# plus browser check for mobile, tablet, desktop breakpoints
```

### Output expectation

Report findings by user impact, not taste:

```text
P0/P1/P2 — [screen/component] — [user problem] — [fix] — [verification]
```
