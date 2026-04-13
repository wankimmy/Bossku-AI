# Design Systems Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Step-by-step workflow for creating a DESIGN.md, auditing an existing design system, or establishing tokens for a project.

## Phase 1: Brand discovery

### 1.1 Gather brand inputs
- What 3 adjectives describe the brand? (e.g., bold, warm, accessible)
- Who is the target audience? Age, technical sophistication, context of use.
- What brands does the user admire visually? (These become reference candidates.)
- What existing brand assets exist? (Logo, colors, typography, style guide, Figma files)

### 1.2 Define the personality axis
Position the brand on these spectrums:
- Playful ←→ Serious
- Luxurious ←→ Accessible
- Bold ←→ Subtle
- Warm ←→ Cool
- Dense ←→ Spacious

### 1.3 Check for existing design system
- Search the project for existing design tokens, theme files, Tailwind config, CSS variables
- If a system exists: switch to audit mode — enhance rather than replace

## Phase 2: Reference calibration

### 2.1 Select reference brands
Choose 2-3 brands from the getdesign.md index that match the personality axis:

| Personality | Good references |
|-------------|----------------|
| Bold + warm | Airbnb, Nike, Spotify |
| Clean + professional | Stripe, Linear, Vercel |
| Luxurious + precise | Ferrari, Bugatti, Apple |
| Technical + dense | PostHog, Sentry, ClickHouse |
| Creative + playful | Figma, Framer, Miro |
| Fintech + trustworthy | Coinbase, Wise, Revolut |

### 2.2 Fetch and analyze references
- Fetch DESIGN.md from `getdesign.md/{company}/design-md` using WebFetch
- Extract structural patterns: how tokens are organized, component depth, responsive approach
- Note the quality bar: level of specificity in color roles, typography hierarchy, shadow systems
- **Do not copy values** — use references for structure and quality calibration only

### 2.3 Offline fallback
If getdesign.md is unavailable, use the 9-section format structure directly and work from user-provided brand inputs without external references.

## Phase 3: Visual theme and atmosphere (Section 1)

- Write 2-3 sentence brand atmosphere description
- Define visual keywords (e.g., "editorial minimalism", "tech-forward warmth", "automotive precision")
- Describe the mood: what should the user feel when interacting with this product?
- Specify design density: airy with lots of whitespace, or information-dense?

## Phase 4: Color palette and roles (Section 2)

### 4.1 Primitive palette
Define 5-8 base colors with hex codes. Each color needs a name:
```
--color-brand-red: #ff385c
--color-neutral-900: #222222
--color-neutral-100: #f7f7f7
```

### 4.2 Semantic roles
Map primitives to functional roles:
```
--color-primary: var(--color-brand-red)
--color-text-primary: var(--color-neutral-900)
--color-bg-primary: #ffffff
--color-success: #00a699
--color-warning: #ffb400
--color-error: #c13515
```

### 4.3 Interactive states
Define hover, focus, active, and disabled color treatments.

### 4.4 Dark mode (if applicable)
Map every semantic token to its dark-mode equivalent.

### 4.5 Contrast verification
Test every text-on-background combination against WCAG AA:
- Body text: minimum 4.5:1
- Large text (18px+ or 14px+ bold): minimum 3:1

## Phase 5: Typography rules (Section 3)

### 5.1 Font families
```
Display: "Inter", system-ui, sans-serif
Body: "Inter", system-ui, sans-serif
Mono: "JetBrains Mono", "Fira Code", monospace
```

### 5.2 Hierarchy table

| Level | Size | Weight | Line-height | Letter-spacing | Use |
|-------|------|--------|-------------|----------------|-----|
| h1 | 48px | 700 | 1.1 | -0.02em | Page titles |
| h2 | 36px | 700 | 1.2 | -0.01em | Section headers |
| h3 | 28px | 600 | 1.3 | 0 | Subsections |
| h4 | 22px | 600 | 1.4 | 0 | Card headers |
| h5 | 18px | 600 | 1.4 | 0 | Labels |
| h6 | 16px | 600 | 1.5 | 0.01em | Small labels |
| body | 16px | 400 | 1.6 | 0 | Default text |
| small | 14px | 400 | 1.5 | 0 | Secondary text |
| caption | 12px | 400 | 1.4 | 0.02em | Captions |
| overline | 12px | 600 | 1.4 | 0.08em | Category labels |

### 5.3 Responsive scaling
Define how typography scales across breakpoints — fluid clamp, step-down, or ratio-based.

### 5.4 Font loading
Specify font-display strategy, critical weight preloading, and fallback fonts.

## Phase 6: Component stylings (Section 4)

For each component, define **all states**: default, hover, active, focus, disabled, loading (where applicable).

### 6.1 Buttons
- Primary: filled with brand color
- Secondary: outlined or muted fill
- Ghost: text-only with hover background
- Destructive: error-colored
- Each needs: padding, border-radius, font-size, font-weight, transition, focus ring

### 6.2 Cards
- Default card: padding, border, shadow, border-radius
- Interactive card: hover shadow lift, cursor pointer
- Variants: outlined, elevated, flat

### 6.3 Inputs
- Text input: border, padding, font-size, placeholder color
- States: default, focus (ring + border color), error (red border + message), disabled (opacity), filled
- Select, checkbox, radio: match the input system

### 6.4 Navigation
- Header: height, background, logo placement, link styles
- Mobile: hamburger trigger, drawer or overlay, animation

### 6.5 Feedback
- Toast: position, colors per severity, animation
- Banner: full-width, dismissible, severity colors
- Tooltip: background, arrow, max-width, delay
- Badge: size, colors, pill vs dot

## Phase 7: Layout principles (Section 5)

### 7.1 Grid system

| Breakpoint | Columns | Gutter | Margin |
|------------|---------|--------|--------|
| < 640px | 4 | 16px | 16px |
| 640-1024px | 8 | 24px | 32px |
| > 1024px | 12 | 24px | auto (max-width) |

### 7.2 Spacing scale
Base unit + multiplier scale:
```
--space-1: 4px    --space-2: 8px    --space-3: 12px
--space-4: 16px   --space-5: 20px   --space-6: 24px
--space-8: 32px   --space-10: 40px  --space-12: 48px
--space-16: 64px  --space-20: 80px  --space-24: 96px
```

### 7.3 Border-radius
```
--radius-none: 0    --radius-sm: 4px    --radius-md: 8px
--radius-lg: 12px   --radius-xl: 16px   --radius-full: 9999px
```

### 7.4 Max content width
Define max-width for content containers (typically 1200-1440px).

## Phase 8: Depth and elevation (Section 6)

### 8.1 Shadow tokens
```
--shadow-sm: 0 1px 2px rgba(0,0,0,0.05)
--shadow-md: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)
--shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)
--shadow-xl: 0 20px 25px rgba(0,0,0,0.1), 0 8px 10px rgba(0,0,0,0.04)
```

### 8.2 Z-index scale
```
--z-dropdown: 100   --z-sticky: 200    --z-overlay: 300
--z-modal: 400      --z-popover: 500   --z-toast: 600
```

### 8.3 Overlays
```
--overlay-light: rgba(255,255,255,0.6)
--overlay-dark: rgba(0,0,0,0.5)
```

## Phase 9: Do's and don'ts (Section 7)

Write 5-8 brand-specific rules. Format:

```
DO: Use the brand accent color sparingly — only for primary CTAs and key highlights.
DON'T: Apply the accent color to large surface areas or backgrounds.

DO: Maintain generous whitespace between sections (min --space-16).
DON'T: Pack content edge-to-edge without breathing room.
```

Include anti-generic-AI rules:
- No predictable purple-to-blue gradients
- No stock illustration styles unless they match the brand
- No cookie-cutter hero sections with centered text over a gradient

## Phase 10: Responsive behavior (Section 8)

### 10.1 Breakpoints
```
--bp-sm: 640px   --bp-md: 768px   --bp-lg: 1024px
--bp-xl: 1280px  --bp-2xl: 1536px
```

### 10.2 Component behavior changes
Document what changes at each breakpoint: navigation collapses, grid column reflow, typography scale-down, card stacking, table scrolling.

### 10.3 Touch targets
All interactive elements: minimum 44x44px on touch devices.

## Phase 11: Agent prompt guide (Section 9)

### 11.1 System prompt
Write a concise system prompt that an AI agent can use to generate on-brand UI:
```
You are building UI for [Brand]. Use these tokens: primary=#xxx, bg=#xxx, text=#xxx.
Typography: [Font] at weights 400/600/700. Spacing base: 8px.
Style: [2-3 adjective summary]. Avoid: [anti-patterns].
```

### 11.2 Component generation templates
Quick-reference prompts for common components:
- "Create a primary button using the design system tokens"
- "Build a card component with hover elevation"
- "Design the mobile navigation drawer"

### 11.3 Quick reference table
Condensed token table for fast AI lookups — colors, fonts, spacing in one scannable block.

## Phase 12: Pencil integration (optional)

When `.pen` design files are part of the project:

1. Use `get_variables` to read existing design tokens from the .pen file
2. Compare with DESIGN.md tokens — flag mismatches
3. Use `set_variables` to sync tokens from DESIGN.md into the .pen file
4. Use `batch_design` to create component specimens matching the DESIGN.md
5. Use `get_screenshot` to visually validate output against specifications

## Output

- **Generation**: Complete DESIGN.md in the 9-section format, ready to place in the project root
- **Audit**: Gap analysis with severity tags (critical/major/minor) and specific remediation steps
- **Token sync**: Updated token files (CSS variables, Tailwind config, or .pen variables) matching the DESIGN.md
