---
name: bosskuai-taste
description: "Use when creating or redesigning landing pages, portfolios, or marketing UI that needs a distinctive design direction and must avoid generic AI aesthetics; pair with implementation, design-system, accessibility, mobile, or Anti-Slop skills only when the prompt needs them."
---

# BosskuAI Taste - Anti-Slop Frontend

The default look an LLM reaches for is the Tell. This skill exists to reach past
it. Scope: landing pages, portfolios, marketing sites, redesigns - **not**
dashboards, data tables, or multi-step product UI. Every rule is contextual:
read the brief first, then pull only what fits.

## 0. Read the room first

- **Output a one-line "Design Read"** before generating: who it's for, the mood, the closest real-world reference. e.g. *"Premium consumer cookware - warm, tactile, editorial; closest to Our Place × Aesop."*
- **If the brief is ambiguous, ask ONE question.** Don't guess the whole direction.
- **Anti-Default Discipline.** Do not default to: AI-purple/violet gradients, centered hero over a dark mesh, three equal feature cards, glassmorphism on everything, infinite micro-animations, `Inter + slate-900`. These are the LLM defaults - reach past them deliberately based on the design read.

## 1. The three dials

Set them from the design read; they gate every layout/motion/density call. Baseline `8 / 6 / 4`, overridden conversationally (never ask the user to edit a file).

- **DESIGN_VARIANCE** (1 symmetry → 10 artsy chaos)
- **MOTION_INTENSITY** (1 static → 10 cinematic/physics)
- **VISUAL_DENSITY** (1 airy → 10 packed)

Presets: minimalist/editorial `5-6 / 3-4 / 2-3`; premium-consumer `7-8 / 5-7 / 3-4`; agency/Awwwards `9-10 / 8-10 / 3-4`; landing (default) `7-9 / 6-8 / 3-5`; trust-first/regulated `3-4 / 2-3 / 4-5`. Redesign-preserve = match existing; redesign-overhaul = +2/+2/match.

## 2. Real systems over hand-rolled

If the brief reads as a known system (Material 3, Fluent, Carbon, HIG, shadcn/ui aesthetic), install and use the **official** package - don't recreate its CSS by hand or import its tokens then override 90%. **One system per project**; never mix (no Material + shadcn in one tree).

## 3. Design-engineering directives (bias correction)

- **Typography.** Avoid `Inter` as the default; control hierarchy with weight + color, not raw scale. Serif only for editorial/luxury/publication. **Banned default display serifs:** `Fraunces`, `Instrument_Serif`. Italic words with descenders (`y g j p q`) get `leading-[1.1]` + `pb-1`. Only fonts you can load: Google Fonts, Fontsource, Fontshare, or the brand's licensed files.
- **Color.** No AI-purple gradient slop as the default reach. No pure black (`#000`) - use off-black/zinc-950. No oversaturated accents, no excessive gradient text. **One accent color used identically across all sections.** Premium-consumer? Avoid the AI-default beige+brass+oxblood+espresso family.
- **Layout.** No 3-column equal feature cards - use 2-col zig-zag, asymmetric grid, scroll-pinned, or horizontal-scroll. No two adjacent sections sharing a layout family (≥4 families across 8 sections). Hero: headline ≤2 lines, subtext ≤20 words, CTA above the fold, ≤4 text elements, `pt-24` max. Trust strip / pricing teaser / feature bullets move *below* the hero, never inside it.
- **Materiality.** One corner-radius system. Inner borders / subtle tinted shadows over neon outer glows. Omit cards in favor of spacing where possible.
- **Real images, not fake divs.** A text page with `<div>` fake screenshots is slop. Priority: generated images → `https://picsum.photos/seed/{descriptive}/{w}/{h}` → explicit placeholder slot. Even minimalist sites need 2-3 real images. Logo walls only for customers or integrations the user confirmed, with their real SVG marks (Simple Icons / devicon), never `<span>Acme</span>` text wordmarks; no confirmed logos, no wall.
- **Icons** from Phosphor / HugeIcons / Radix / Tabler only - no hand-rolled SVG paths; Lucide on explicit request.
- **Theme lock.** One theme (light/dark/auto) for the whole page; no mid-page inversion. Dark-mode tokens tested in both modes.
- **Viewport stability.** `min-h-[100dvh]`, never `h-screen`. Wrap reduced-motion for any `MOTION_INTENSITY > 3`. Motion only via `useScroll()` / ScrollTrigger / IntersectionObserver / CSS scroll-driven - never raw `window.addEventListener('scroll')`. Isolate motion in `'use client'` leaf components with cleanup.

## 4. AI Tells - forbidden by default

**Content slop (these apply to ALL generated copy, not just frontend):**
- No invented people, companies, numbers, reviews or dates. Use what the user supplied; otherwise an honest placeholder ([REAL DATA], Your Name, initials avatar) or drop the section. Swapping a placeholder for a more believable invention (47.2% instead of a round number) is fabrication, not taste.
- No startup-slop brands ("Acme", "Nexus", "SmartFlow", "Cloudly") → the real name, or [BRAND].
- No filler verbs ("Elevate", "Seamless", "Unleash", "Next-Gen", "Revolutionize") → concrete verbs.
- No generic avatars (SVG "egg", Lucide user icon) → initials or a labelled placeholder until real photos exist.

**Visual/structure Tells:** no version eyebrows in hero (`V0.6`, `BETA`, `INVITE-ONLY`) unless it's a launch; no section-number eyebrows (`00 / INDEX`, `001 · Capabilities`); middle-dot `·` rationed to max 1/line; no decorative status dots on every row; no `<br>`-broken italic headlines; no vertical rotated text; no decoration text strips at hero bottom (`BRAND. MOTION. SPATIAL.`); no floating top-right sub-text in section headers; no fake version footers (`v1.4.2`, `last sync 4s ago`); no "Quietly in use at"; no weather/locale strips; no scroll cues; no `border-t`+`border-b` on every list row; no scoring bars with filled background tracks; no pills/captions overlaid on images; no custom mouse cursors.

**Em-dash ban:** zero `-` anywhere - headlines, body, pills, quotes, attribution, captions, buttons, alt text. Use the hyphen `-`.

## 5. Final pre-flight (run before delivering - not optional)

Tick every box; if one fails honestly, it's not done:

- [ ] Design Read declared; dial values reasoned from the brief, not silent baseline.
- [ ] Real design system chosen (if applicable) or aesthetic labeled honestly; one system per project.
- [ ] **Zero em-dashes** anywhere. One accent color, one radius system, one page theme.
- [ ] Every CTA passes WCAG AA contrast and doesn't wrap to 2 lines; forms/placeholders/focus rings pass AA.
- [ ] Hero fits viewport (≤2-line headline, CTA above fold, ≤4 elements, `pt-24` max); no trust micro-strip inside hero.
- [ ] No 3-equal-cards; ≥4 distinct layout families across sections; no 3+ consecutive identical zig-zags.
- [ ] Real images used (no div fake-screenshots, no hand-rolled decorative SVG, no pure-text "minimalism"). Logo wall only with confirmed real logos (SVG marks, under the hero).
- [ ] No AI Tells from §4 (Inter default, AI-purple, invented people/numbers/logos, Acme, filler verbs, decoration strips, fake version footers).
- [ ] Motion justified in one sentence each; reduced-motion wrapped; `min-h-[100dvh]`; empty/loading/error states present.
- [ ] Copy self-audit: every visible string re-read for AI-hallucinated/broken phrasing.

## 6. Stack and precedence

- Load one direction skill: this file, or taste-skill (a superset with the design-system install map, GSAP skeletons and redesign protocol), or hallmark, or one aesthetic (soft-skill, minimalist-skill, brutalist-skill). Never this file together with taste-skill.
- Not this skill: dashboards and app screens -> bosskuai-ui-ux-design-to-code; DESIGN.md or tokens -> bosskuai-design-systems; WCAG audits -> accessibility.
- Gate after the build: antislop + antislop-ui (+ antislop-layoutmobile, antislop-human when needed). When the gate runs, also state its dials: VARIANCE->RHYTHM, MOTION_INTENSITY->MOTION, ENERGY = the higher of the two; 1-3 -> 1, 4-7 -> 2, 8-10 -> 3.
- On conflict, the direction skill decides palette, type, shape and density; the gate decides honesty, accessibility, contrast and reduced motion.

The point isn't more decoration - it's intent. If the explanation for a flourish is "to look designed," delete it.
