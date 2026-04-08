---
name: bosskuai-ui-ux-design-to-code
description: Use this for UI/UX review, interface critique, design systems thinking, mobile-responsive behavior, accessibility (WCAG), and translating designs or screenshots into implementation-ready code guidance.
---

# BosskuAI UI/UX and Design-to-Code

Use this skill for **screens, flows, and implementation-ready UI guidance** — from critique of a design to handoff for engineering.

## How this differs from nearby skills

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
