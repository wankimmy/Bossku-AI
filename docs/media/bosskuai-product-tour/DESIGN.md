# BosskuAI Product Tour Design

## Style Prompt

Product-native developer workflow film. The real BosskuAI emerald and zinc interface is the visual hero. Camera moves are deliberate and cinematic: establish the workspace, push into one proof point, then pull back before the next area. Motion shows control and attention, not urgency.

## Colors

- Canvas: `#09090B`
- Surface: `#18181B`
- Border: `#3F3F46`
- Active: `#059669`
- Focus: `#34D399`
- Decision: `#F59E0B`
- Primary text: `#F4F4F5`
- Secondary text: `#D4D4D8`

## Typography

- `ui-sans-serif, system-ui, sans-serif`
- 700 for scene titles and 500 for support copy
- 60px minimum for titles and 20px minimum for support copy

## Motion

- Scenes use a 0.55-second soft focus pull or cross-dissolve transition.
- Screens enter from a static hero layout using `gsap.from()`.
- Each screen has one slow push or pan while its proof remains readable.
- The final scene fades to black only after a two-second closing hold.

## What NOT to Do

- No gradients, neon glows, fake dashboard art, or unsupported product claims.
- No additional animation inside Pixel Office beyond the captured app state.
- No small text, abrupt cuts, or exits before a scene transition begins.
- No decorative display fonts that compete with the product interface.
