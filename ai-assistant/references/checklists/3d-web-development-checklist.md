# 3D Web Development Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this checklist when creating or reviewing any 3D website or WebGL experience.

## Scene setup

- [ ] Canvas has correct camera position, FOV, and clipping planes
- [ ] 3D content wrapped in `<Suspense>` with meaningful fallback/loader
- [ ] Device pixel ratio set appropriately: `dpr={[1, 2]}`
- [ ] Tone mapping configured (ACESFilmic for cinematic, default for neutral)
- [ ] Color management: `flat={true}` when using post-processing
- [ ] Shadows enabled only where visually necessary (expensive)

## Lighting

- [ ] Ambient light present with low intensity and mood-setting color
- [ ] Key light (spotlight) provides primary illumination and shadows
- [ ] Accent lights (1-2) add colored highlights for depth and drama
- [ ] Fill light (rect area or point) prevents harsh dark areas
- [ ] Environment map loaded for reflective/metallic surfaces
- [ ] Lighting tested in isolation — looks good before adding models

## Models and assets

- [ ] Models in GLB/GLTF format (web standard)
- [ ] Models compressed with Draco or meshopt (target < 2MB per model)
- [ ] Textures optimized: WebP/AVIF, power-of-2 dimensions, max 2048px
- [ ] Models preloaded during loading screen: `useGLTF.preload()`
- [ ] gltfjsx used to generate typed React components from GLB
- [ ] Materials overridden where default export doesn't match design
- [ ] Model animations playing correctly via `useAnimations()` if applicable

## Animation and interaction

- [ ] Scroll animations use GSAP ScrollTrigger with `scrub: true`
- [ ] GSAP timelines organized with clear trigger sections
- [ ] Mouse/pointer interactions implemented (hover, follow, drag)
- [ ] Camera follow uses easing/damping (not raw mouse position)
- [ ] All transitions have appropriate easing (no linear unless intentional)
- [ ] Animation choreography is sequenced (stagger, timeline order)
- [ ] `prefers-reduced-motion` respected — simplified or static fallback
- [ ] No animation runs when tab is not visible (useFrame auto-pauses in R3F)

## Post-processing

- [ ] Effects applied selectively (not everything on every scene)
- [ ] Bloom: selective (using refs), not global (avoids washed-out look)
- [ ] Effect order correct in EffectComposer chain
- [ ] Performance impact measured — disable effects if FPS drops below 30
- [ ] Effects disabled or reduced on mobile devices

## Smooth scroll

- [ ] Lenis or equivalent smooth scroll library integrated
- [ ] Scroll synchronized with GSAP ScrollTrigger
- [ ] Scroll not broken on mobile touch devices
- [ ] Modals and overlays excluded from smooth scroll (`prevent` option)
- [ ] Scroll anchors and navigation still work with smooth scroll

## Performance

- [ ] Target: 60fps on desktop, 30fps minimum on mobile
- [ ] Models: total scene under 5MB uncompressed, under 2MB compressed
- [ ] Textures: no single texture larger than 2048x2048
- [ ] Particles: count reduced on mobile (50-100 vs 100-200 desktop)
- [ ] React.lazy() used for code-splitting 3D-heavy components
- [ ] `useFrame` callbacks are lightweight (no heavy computation per frame)
- [ ] Geometries, materials, and textures disposed on unmount
- [ ] InstancedMesh used for repeated identical objects (> 10 copies)
- [ ] Frustum culling not accidentally disabled
- [ ] Lighthouse performance score checked (target > 70 with 3D)

## Responsive

- [ ] Mobile breakpoint handling: scale, position, camera adjustments
- [ ] Touch devices: tap replaces hover, pinch/zoom handled or disabled
- [ ] OrbitControls: zoom disabled on touch if not needed
- [ ] 3D scale: ~0.5-0.7x on mobile, ~0.8x tablet, 1x desktop
- [ ] Post-processing disabled or reduced on mobile
- [ ] Fallback considered for devices without WebGL support
- [ ] Portrait and landscape orientations tested

## Loading experience

- [ ] Preloader shows progress (percentage, bar, or creative animation)
- [ ] 3D scene hidden until fully loaded (no partial render flash)
- [ ] Preloader animation itself is smooth and on-brand
- [ ] Loading state coordinated between preloader and 3D scene load event

## Visual quality (Awwwards standard)

- [ ] Typography: bold display fonts, proper contrast with 3D background
- [ ] Color palette: limited (2-3 accent colors), consistent with lighting
- [ ] Dark/moody aesthetic leveraged for 3D depth and contrast
- [ ] Custom cursor on interactive 3D elements
- [ ] Transitions between sections are choreographed (not abrupt)
- [ ] Spacing generous — sections breathe, not cramped
- [ ] Loading experience is part of the design (not an afterthought)

## Accessibility

- [ ] All non-3D UI elements keyboard navigable
- [ ] Text has sufficient color contrast (WCAG AA minimum)
- [ ] `prefers-reduced-motion` disables or simplifies animations
- [ ] Alt text or ARIA labels on meaningful visual content
- [ ] 3D interactions have non-3D alternatives where possible
- [ ] Focus indicators visible on all interactive elements

## Advanced (Awwwards-competitive level)

- [ ] At least one custom shader (not just default materials) for visual uniqueness
- [ ] GPU-based particles (FBO/instanced) if particle count > 1000
- [ ] Adaptive quality tiers: high-end desktop / mid-range / mobile
- [ ] KTX2/Basis Universal compressed textures for GPU-native decoding
- [ ] Baked lighting considered for complex static scenes
- [ ] At least one "wow moment" in the first 5 seconds of the experience
- [ ] Page/section transitions are 3D-aware (morph, dissolve, not hard-cut)
- [ ] Sound design: ambient audio or interaction sounds with toggle
- [ ] Total compressed page weight under 5-8MB
- [ ] Time to interactive under 3 seconds on mid-range devices

## Cross-browser

- [ ] Chrome: primary target, test first
- [ ] Safari/WebKit: test WebGL compatibility, shader differences
- [ ] Firefox: verify performance parity
- [ ] Mobile Safari: test touch, scroll, and WebGL rendering
- [ ] Edge: generally Chrome-compatible, verify
