# Design Systems Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this checklist when creating, auditing, or applying a design system or DESIGN.md.

## Brand foundation

- [ ] Brand personality defined (3+ adjectives, audience, competitive positioning)
- [ ] 2-3 reference brands identified and consulted for structural calibration (not copied)
- [ ] Visual theme statement written (2-3 sentences capturing mood and aesthetic philosophy)

## Color palette and roles

- [ ] Primitive palette defined (5-8 base colors with hex codes)
- [ ] Semantic roles assigned: primary, secondary, accent, success, warning, error, info
- [ ] Background, surface, and text roles defined with named tokens
- [ ] Dark mode mapping defined (if applicable)
- [ ] All text-background pairs pass WCAG AA contrast (4.5:1 body, 3:1 large text)
- [ ] Interactive state colors defined: hover, focus ring, active, disabled

## Typography

- [ ] Font families chosen: display, body, mono (with web-safe fallbacks)
- [ ] Full hierarchy table: h1-h6, body, small, caption, overline — with size, weight, line-height, letter-spacing
- [ ] Responsive scaling approach defined (fluid clamp, breakpoint steps, or scale factor)
- [ ] Web font loading strategy specified (font-display: swap, preload critical weights)

## Components

- [ ] Buttons: all variants (primary, secondary, ghost, destructive) with all states (default, hover, active, focus, disabled, loading)
- [ ] Cards: variants defined with padding, border, shadow, and hover behavior
- [ ] Inputs: text, select, checkbox, radio — all states (default, focus, error, disabled, filled)
- [ ] Navigation: header pattern + mobile nav pattern specified
- [ ] Feedback: toast, banner, tooltip, badge patterns defined

## Layout and spacing

- [ ] Grid system defined: columns, gutters, margins per breakpoint
- [ ] Spacing scale defined with base unit (4px or 8px) and named tokens
- [ ] Border-radius system defined: none, sm, md, lg, full
- [ ] Max content width set

## Depth and elevation

- [ ] Shadow tokens defined: sm, md, lg, xl (with multi-layer stacks where appropriate)
- [ ] Z-index scale documented with semantic names
- [ ] Overlay opacity values defined (backdrop, scrim)

## Responsive behavior

- [ ] Breakpoints defined with specific pixel values
- [ ] Component behavior changes documented per breakpoint
- [ ] Touch targets minimum 44x44px on mobile
- [ ] Navigation collapse/transformation pattern documented

## Design rules

- [ ] At least 5 brand-specific do's and don'ts defined
- [ ] Anti-patterns called out with concrete examples
- [ ] Anti-generic-AI aesthetics enforced (no stock gradients, predictable palettes, cookie-cutter layouts)

## Agent prompt guide

- [ ] System prompt for on-brand UI generation included
- [ ] Component generation prompt templates included
- [ ] Quick color/font reference table for fast AI lookups

## DESIGN.md format compliance

- [ ] All 9 sections of the Google Stitch format are present
- [ ] Every token has a semantic role (no bare hex codes without names)
- [ ] Every interactive component has all state definitions
- [ ] The document is self-contained — an AI agent can generate on-brand UI from it alone
