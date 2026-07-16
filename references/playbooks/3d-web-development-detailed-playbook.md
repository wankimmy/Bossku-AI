# 3D Web Development Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Step-by-step workflow for creating Awwwards-quality 3D websites.

## Phase 1: Discovery and approach selection

### 1.1 Classify the project type
- **Hero 3D** — Single 3D element as visual anchor on an otherwise 2D page
- **Scroll-driven 3D** — 3D objects transform as user scrolls through sections
- **Full 3D experience** — Entire page is a WebGL-rendered environment
- **Product showcase** — Interactive 3D model with configurator/viewer
- **Hybrid** — Mix of 3D canvas and 2D UI (most Awwwards sites)

### 1.2 Choose the technology stack

| Scenario | Recommended stack |
|----------|-------------------|
| React project, custom 3D | React Three Fiber + Drei + Three.js |
| React project, designer-created 3D | Spline + @splinetool/react-spline |
| Scroll-heavy interactions | GSAP + ScrollTrigger + Lenis |
| Simple globe or single effect | Cobe, or vanilla Three.js |
| Maximum control, custom shaders | Vanilla Three.js + GLSL |
| Next.js project | R3F with dynamic imports (no SSR for Canvas) |

### 1.3 Define the performance budget
- Total 3D assets: < 5MB uncompressed
- Largest single model: < 2MB
- Target FPS: 60 desktop, 30+ mobile
- Time to interactive: < 4 seconds on 4G
- Lighthouse performance: > 70

## Phase 2: Scene design (before code)

### 2.1 Design the scene graph
```
Scene
├── Camera (perspective, FOV 45-75)
├── Lighting
│   ├── AmbientLight (base mood)
│   ├── SpotLight (key)
│   ├── SpotLight (accent 1, colored)
│   ├── SpotLight (accent 2, colored)
│   ├── RectAreaLight (fill)
│   └── PointLight (atmospheric, 1-2)
├── Models
│   ├── Hero model (primary visual)
│   ├── Environment (optional)
│   └── Decorative elements
├── Particles (optional)
├── Post-processing
│   ├── SelectiveBloom
│   ├── Vignette
│   └── [other effects]
└── Controls/Interactions
    ├── OrbitControls or custom camera rig
    ├── Mouse follow
    └── Scroll bindings
```

### 2.2 Plan the animation choreography
Map out what happens at each scroll position / interaction:
```
0%   scroll — Hero visible, model idle animation, particles falling
25%  scroll — Model rotates, camera pulls back, section 2 text fades in
50%  scroll — Model scales down, moves to side, skills/features grid appears
75%  scroll — Model transitions, projects section with hover previews
100% scroll — Contact section, model settled in final position
```

### 2.3 Plan responsive behavior
| Element | Desktop | Tablet | Mobile |
|---------|---------|--------|--------|
| Model scale | 1.0 | 0.8 | 0.5-0.7 |
| Camera distance | Normal | Slightly closer | Close |
| Particles | 150-200 | 100 | 50 |
| Post-processing | Full | Reduced | Off |
| OrbitControls | Full | No zoom | Disabled |
| Lighting | Full rig | Simplified | Ambient + 1 key |

## Phase 3: Project setup

### 3.1 Initialize project
```bash
# Vite + React (recommended for portfolios)
npm create vite@latest project-name -- --template react
cd project-name

# Core 3D
npm install three @react-three/fiber @react-three/drei

# Post-processing (if needed)
npm install @react-three/postprocessing

# Animation
npm install gsap @gsap/react

# Smooth scroll
npm install lenis

# Motion (for 2D + spring physics)
npm install motion

# Styling
npm install tailwindcss @tailwindcss/vite

# Responsive
npm install react-responsive
```

### 3.2 Project structure
```
src/
├── App.jsx
├── main.jsx
├── constants/
│   └── index.js              (content data, 3D config)
├── sections/
│   ├── Hero.jsx              (3D canvas + overlay text)
│   ├── About.jsx
│   ├── Projects.jsx
│   ├── Experience.jsx
│   ├── Contact.jsx
│   └── Footer.jsx
├── components/
│   ├── canvas/
│   │   ├── Scene.jsx         (main 3D scene wrapper)
│   │   ├── Lights.jsx        (lighting rig)
│   │   ├── Model.jsx         (primary 3D model)
│   │   ├── Particles.jsx     (particle system)
│   │   ├── Rig.jsx           (camera controller)
│   │   └── Effects.jsx       (post-processing chain)
│   ├── ui/
│   │   ├── Preloader.jsx     (loading experience)
│   │   ├── Navbar.jsx
│   │   ├── GlowCard.jsx      (interactive card)
│   │   └── AnimatedText.jsx  (text reveal)
│   └── shared/
│       ├── SmoothScroll.jsx   (Lenis wrapper)
│       └── Loader.jsx         (3D Suspense fallback)
└── assets/
    ├── models/               (GLB files)
    ├── textures/             (environment maps, matcaps)
    └── images/               (backgrounds, parallax layers)
```

### 3.3 Next.js specific setup
```jsx
// Dynamic import with SSR disabled (Canvas doesn't work server-side)
import dynamic from "next/dynamic";
const Scene = dynamic(() => import("@/components/canvas/Scene"), { ssr: false });
```

## Phase 4: Implementation

### 4.1 Build order (dependencies flow downward)
1. **Preloader** — first thing user sees
2. **Canvas + Camera** — empty scene with correct camera
3. **Lighting rig** — verify lighting looks good before adding models
4. **Model loading** — hero model, test in isolation
5. **Model animation** — idle, hover, and scroll-driven
6. **Particles** — ambient visual enhancement
7. **Post-processing** — bloom, vignette, etc.
8. **Smooth scroll** — Lenis integration
9. **Scroll animations** — GSAP ScrollTrigger bindings
10. **Mouse interactions** — camera follow, hover effects
11. **2D UI sections** — text, grids, cards on top of 3D
12. **Responsive** — mobile/tablet adaptations
13. **Polish** — custom cursor, micro-interactions, transitions

### 4.2 Common pitfalls to avoid
- **Loading flash**: Canvas renders before model loads → use Suspense + preloader
- **Z-fighting**: Overlapping 3D surfaces flicker → adjust near/far planes
- **Mobile crash**: Too many particles/effects on mobile → detect and reduce
- **Scroll jank**: Heavy useFrame + scroll listener conflict → use GSAP scrub instead
- **Memory leak**: Textures/geometries not disposed → cleanup in useEffect return
- **SSR crash**: Canvas used in Next.js without dynamic import → always use `ssr: false`
- **Bloom blowout**: Global bloom washes everything out → use SelectiveBloom with refs
- **Stale refs**: useFrame accessing unmounted refs → null check before access
- **Touch scroll**: Smooth scroll breaks native touch → test on real devices
- **Font flash**: Custom fonts load after 3D → preload fonts in HTML head

## Phase 5: Polish and optimization

### 5.1 Performance optimization checklist
1. Run Chrome DevTools Performance tab — identify frame drops
2. Check GPU memory usage — models and textures are the usual suspects
3. Enable Draco compression on GLB files
4. Add React.lazy() boundaries around 3D components
5. Reduce texture resolution where not visible at rendered size
6. Use InstancedMesh for any repeated geometry
7. Profile useFrame callbacks — remove unnecessary computations
8. Test on a mid-range phone (not just your dev machine)

### 5.2 Visual polish checklist
1. Preloader feels premium (animated, on-brand, smooth)
2. Section transitions are choreographed (not just fade-in)
3. Hover states on all interactive elements
4. Custom cursor for 3D interaction areas
5. Color palette consistent between 3D lighting and 2D UI
6. Typography hierarchy clear: display, heading, body
7. Spacing is generous and consistent
8. Dark theme leveraged for 3D depth

### 5.3 Accessibility pass
1. `prefers-reduced-motion` media query respected
2. All buttons/links keyboard accessible
3. Text contrast meets WCAG AA (4.5:1 body, 3:1 large)
4. Screen reader gets meaningful content (not just Canvas)
5. Focus visible on all interactive elements

## Phase 6: Deployment

### 6.1 Build optimization
- Verify production build runs without errors
- Check bundle size — 3D libraries are large, ensure tree-shaking works
- Static assets (GLB, textures) served from CDN if possible
- Enable gzip/brotli compression on server
- Set cache headers for static 3D assets (they rarely change)

### 6.2 Pre-launch verification
- Test on target devices (desktop Chrome, mobile Safari, tablet)
- Lighthouse audit (Performance, Accessibility, Best Practices)
- Check for console errors and WebGL warnings
- Verify preloader → scene transition is seamless
- Test with slow network (3G throttle) — loading experience still works
- Verify `prefers-reduced-motion` fallback

## Quick reference: library combinations

| Stack | When to use |
|-------|-------------|
| R3F + Drei + GSAP + Lenis | Full-featured 3D portfolio (most flexible) |
| Spline + Framer Motion + GSAP | Designer-created 3D, rapid development |
| R3F + Drei + Motion | React-focused, spring physics, simpler scenes |
| Cobe + GSAP | Globe-focused section (lightweight) |
| Vanilla Three.js + GSAP | Non-React project or maximum control |
