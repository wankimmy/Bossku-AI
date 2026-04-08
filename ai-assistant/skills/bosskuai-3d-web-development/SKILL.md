---
name: bosskuai-3d-web-development
description: Use this for creating immersive 3D websites, WebGL experiences, Three.js/React Three Fiber scenes, scroll-driven 3D animations, GSAP-powered 3D motion, post-processing effects, Spline integrations, interactive particle systems, and Awwwards-quality 3D web experiences.
---

# BosskuAI 3D Web Development Expert

Use this skill when creating or reviewing 3D websites, immersive web experiences, WebGL-powered pages, interactive portfolios, product showcases, or any site that uses Three.js, React Three Fiber, Spline, or similar 3D web technologies. This skill produces Awwwards-quality 3D websites.

For 2D DOM/SVG motion without WebGL, use `bosskuai-gsap-animation`. For smooth scrolling and scroll plumbing without a 3D scene, use `bosskuai-lenis-smooth-scroll`.

## Core technology stack knowledge

### Primary 3D frameworks (pick the right one)

| Library | Best for | Trade-off |
|---------|----------|-----------|
| **Three.js** | Full control, custom shaders, complex scenes | More code, steeper curve, maximum flexibility |
| **React Three Fiber (R3F)** | React projects, declarative 3D, component reuse | React ecosystem lock-in, excellent DX |
| **@react-three/drei** | Pre-built R3F helpers (Float, OrbitControls, useGLTF, Environment, Text3D, MeshTransmission) | Reduces boilerplate dramatically |
| **@react-three/postprocessing** | Bloom, depth of field, vignette, chromatic aberration, god rays | GPU cost, use selectively |
| **Spline (@splinetool/react-spline)** | Designer-friendly 3D, cloud-hosted scenes, no-code 3D creation | Less control, external dependency, larger payload |
| **Cobe** | Lightweight interactive globes | Single-purpose, very small bundle |
| **OGL** | Lightweight WebGL (~5x smaller than Three.js), studios like Active Theory use it | Less ecosystem, more manual work |
| **Troika-three-text** | High-quality SDF text rendering in 3D | Better than Text3D for dynamic text |

### Animation & motion

| Library | Use case |
|---------|----------|
| **GSAP + ScrollTrigger** | Scroll-synced animations, timeline sequencing, complex easing, scrub-based 3D transforms |
| **Framer Motion / Motion** | React-native animations, layout transitions, gesture-driven motion, AnimatePresence |
| **Lenis** | Smooth scroll (buttery 60fps scrolling that syncs with GSAP ScrollTrigger) |
| **Maath (easing)** | Smooth damped camera follow, interpolation utilities inside useFrame |
| **Spring physics** | useSpring + useMotionValue for organic, physics-based transitions |

### Supporting tools

| Tool | Purpose |
|------|---------|
| **gltfjsx** | Auto-generate React components from GLB/GLTF models |
| **Draco / meshopt** | Model compression (reduce GLB size 60-90%) |
| **KTX2 / Basis Universal** | GPU-native texture compression (much smaller than PNG/JPG) |
| **HDR / EXR environments** | Realistic reflections and ambient lighting |
| **Shader chunks / GLSL** | Custom visual effects (noise, distortion, gradient, dissolve) |
| **Noise functions (Simplex/Perlin/Worley)** | Organic movement, procedural textures, generative effects |
| **Blender** | 3D model creation, baking textures/lighting, optimizing geometry |
| **FBO (Frame Buffer Object)** | GPU-computed particle positions for massive counts (100k+) |
| **GSAP Flip plugin** | Smooth page/section transitions that morph 3D scenes |

## Workflow

1. **Classify the 3D approach** — Determine which category the project falls into:
   - **Hero 3D scene** — Single interactive 3D element as visual anchor (most common)
   - **Full 3D experience** — Entire page is a 3D environment with camera-driven navigation
   - **Scroll-driven 3D** — 3D objects transform based on scroll position (GSAP scrub)
   - **3D product showcase** — Rotating/interactive product model with environment
   - **Particle/generative** — WebGL particles, noise fields, generative backgrounds
   - **Hybrid** — 3D elements layered with traditional 2D UI (most Awwwards sites)

2. **Design the scene graph** before writing code:
   - Camera type and position (perspective vs orthographic, FOV, near/far)
   - Lighting rig (ambient base + accent spots + fill + environment map)
   - Model hierarchy (groups, transforms, conditional visibility)
   - Post-processing chain (which effects, in what order)
   - Interaction model (orbit, scroll-driven, mouse-follow, click-to-reveal)

3. **Set up the Canvas correctly**:
   ```
   Canvas setup checklist:
   - Camera: position, FOV, near/far clipping planes
   - Suspense: ALWAYS wrap 3D content with fallback loader
   - Responsive: useMediaQuery to adjust scale/position per device
   - Performance: dpr={[1, 2]} for retina without GPU waste
   - Tone mapping: ACESFilmic for cinematic look
   - Color management: flat={true} when using post-processing
   - Shadows: only when necessary (expensive)
   ```

4. **Build the lighting rig** (critical for visual quality):
   ```
   Awwwards-quality lighting recipe:
   - ambientLight: low intensity (0.1-0.3), tinted color for mood
   - 1 key spotLight: dominant directional, high intensity
   - 1-2 accent spotLights: colored (purple #9d4edd, blue #4cc9f0, warm #ff6b35)
   - 1 rectAreaLight: soft fill from above/side
   - 1-2 pointLights: atmospheric glow, deep colors
   - Environment map: for realistic reflections on metallic/glass surfaces
   ```

5. **Load and display 3D models**:
   - Use GLB/GLTF format exclusively (web standard)
   - Load with `useGLTF()` from drei, preload with `useGLTF.preload()`
   - Generate components with gltfjsx for type-safe mesh access
   - Override materials when the default look doesn't match the design
   - Use `<Float>` from drei for ambient bobbing motion
   - Handle animations with `useAnimations()` hook

6. **Implement scroll-driven 3D animations**:
   ```
   GSAP ScrollTrigger pattern:
   - Create timeline with scrollTrigger config
   - scrub: true for scroll-synced (or scrub: 1 for smooth)
   - Pin sections when 3D needs to transform during scroll
   - Use gsap.to() to animate 3D object properties (scale, position, rotation)
   - onEnter/onLeaveBack callbacks for state changes
   - Stagger children for sequential reveals
   ```

7. **Add post-processing effects** (selective, not all at once):
   ```
   Common Awwwards effect chains:
   - SelectiveBloom: glow on emissive elements (screens, lights, accents)
   - Vignette: subtle darkening at edges for cinematic framing
   - ChromaticAberration: slight color split for stylized look
   - DepthOfField: focus on hero element, blur background
   - Noise/Film grain: subtle texture for organic feel
   - God rays: volumetric light shafts from bright sources
   ```

8. **Implement mouse/pointer interactions**:
   ```
   Interaction patterns:
   - Camera follow: useFrame + easing.damp3() for smooth mouse-driven camera
   - Object hover: onPointerEnter/Leave with scale/color transitions
   - Parallax layers: useMotionValue + useTransform for depth
   - Cursor effects: custom cursor that reacts to 3D hover states
   - Drag: useDrag for interactive object manipulation
   ```

9. **Build particle systems** when needed:
   ```
   Particle system approach:
   - Use BufferGeometry + Float32Array for positions
   - PointsMaterial with size, opacity, color
   - Animate in useFrame: update positions each frame
   - Mouse attraction: calculate distance, apply magnetism force
   - Edge recycling: respawn particles when they leave bounds
   - Count: 50-200 (balance visual density vs performance)
   ```

10. **Integrate 3D with 2D UI** (the hybrid approach most Awwwards sites use):
    ```
    Integration patterns:
    - Canvas as background: absolute positioning, z-index layering
    - Text overlay: HTML content positioned over Canvas
    - Split layout: text on one side, 3D on the other (responsive grid)
    - Scroll sections: full-height sections with Canvas spanning viewport
    - Portal: drei's <Html> component for DOM inside 3D scene
    - Pointer events: pointer-events-none on overlays that shouldn't block 3D
    ```

11. **Apply Awwwards-quality visual design principles**:
    - **Typography**: Large, bold display fonts contrasting with 3D depth
    - **Color**: Limited palette (2-3 accent colors) with dark/moody backgrounds
    - **Spacing**: Generous whitespace, sections breathe
    - **Transitions**: Every state change is animated (page, section, element)
    - **Loading**: Preloader with progress animation (not just a spinner)
    - **Cursor**: Custom cursor that changes on interactive elements
    - **Sound**: Optional ambient sound or interaction feedback (with user opt-in)
    - **Scroll**: Smooth scroll (Lenis) synchronized with animations

12. **Optimize for performance** (critical — beautiful but slow loses):
    - Compress models: Draco/meshopt compression, reduce polygon count
    - Texture optimization: WebP/AVIF textures, power-of-2 dimensions, max 2048px
    - Lazy load 3D: React.lazy() + Suspense for code splitting
    - Reduce on mobile: fewer particles, simpler lighting, lower-res textures
    - Instancing: InstancedMesh for repeated objects
    - Frustum culling: default in Three.js, verify not disabled
    - Dispose: clean up geometries, materials, textures on unmount
    - Frame budget: target 60fps, use `useFrame` wisely (avoid heavy computation)
    - Conditional rendering: skip heavy effects on low-power devices
    - Preload critical models: `useGLTF.preload()` during loading screen

13. **Handle responsive 3D** (most 3D sites fail here):
    ```
    Responsive strategy:
    - useMediaQuery for device detection (mobile < 768px, tablet < 1024px)
    - Scale 3D scenes: 0.5-0.7x on mobile, 0.8x tablet, 1x desktop
    - Reposition camera: closer on mobile, wider on desktop
    - Simplify: fewer lights, no post-processing on mobile
    - Touch: replace hover with tap, disable OrbitControls zoom on touch
    - Orientation: handle portrait vs landscape camera adjustments
    - Fallback: consider 2D fallback for very old devices / no WebGL
    ```

14. **Test and verify**:
    - Visual: does it look as good as the design/reference?
    - Performance: Lighthouse score, FPS counter, no jank
    - Responsive: test mobile, tablet, desktop, ultra-wide
    - Accessibility: prefers-reduced-motion, alt text, keyboard navigation for UI
    - Loading: preloader works, no flash of un-styled content
    - Cross-browser: Chrome, Safari (WebKit differences), Firefox
    - Memory: no leaks (check dispose, cleanup in useEffect return)

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not add post-processing effects without checking GPU and performance budget — effects that look great on desktop can drop mobile to below 30fps.
- Do not use Spline for production-critical scenes without a fallback — external cloud dependency adds latency and single-point failure risk.
- Do not skip responsive adaptation — most 3D sites fail on mobile; test at 375px before calling any scene done.

## Output expectation

- 3D approach classification (hero scene, full experience, scroll-driven, hybrid, etc.)
- Scene graph design (camera, lights, models, effects, interactions)
- Technology selection with rationale (R3F vs Spline vs vanilla Three.js)
- Implementation plan with component structure
- Animation choreography (what moves when, triggered by what)
- Performance budget and optimization strategy
- Responsive adaptation plan
- Code implementation following patterns from reference projects
- Post-processing chain specification
- Loading/preloader strategy

## Code patterns reference (from proven portfolio examples)

### Canvas initialization
```jsx
<Canvas camera={{ position: [0, 0, 15], fov: 45 }} dpr={[1, 2]}>
  <ambientLight intensity={0.2} color="#1a1a40" />
  <Suspense fallback={<Loader />}>
    <group scale={isMobile ? 0.7 : 1}>
      <Scene />
    </group>
    <Lights />
    <EffectComposer>
      <SelectiveBloom intensity={1.5} luminanceThreshold={0.2} />
    </EffectComposer>
  </Suspense>
</Canvas>
```

### Camera mouse-follow (Maath easing)
```jsx
function Rig() {
  return useFrame((state, delta) => {
    easing.damp3(
      state.camera.position,
      [state.mouse.x / 10, 1 + state.mouse.y / 10, 3],
      0.5,
      delta
    );
  });
}
```

### GSAP scroll-driven 3D transform
```jsx
useGSAP(() => {
  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: "#section",
      start: "top bottom",
      end: "bottom top",
      scrub: true,
    },
  });
  tl.to(groupRef.current.rotation, { y: Math.PI / 4 });
  tl.to(groupRef.current.position, { y: -2, x: 1 }, "<");
  tl.to(groupRef.current.scale, { x: 0.8, y: 0.8, z: 0.8 }, "<");
});
```

### Parallax background layers
```jsx
const scrollY = useScroll();
const mountain3Y = useTransform(scrollY.scrollYProgress, [0, 0.5], ["0%", "70%"]);
const planetsX = useTransform(scrollY.scrollYProgress, [0, 0.5], ["0%", "-20%"]);

<motion.div style={{ y: mountain3Y }}>
  <img src="/bg-layer-3.webp" />
</motion.div>
```

### Particle system with mouse attraction
```jsx
function Particles({ count = 100 }) {
  const mesh = useRef();
  const positions = useMemo(() => {
    const arr = new Float32Array(count * 3);
    for (let i = 0; i < count * 3; i++) arr[i] = (Math.random() - 0.5) * 10;
    return arr;
  }, [count]);

  useFrame(() => {
    const pos = mesh.current.geometry.attributes.position.array;
    for (let i = 1; i < pos.length; i += 3) {
      pos[i] -= 0.005 + Math.random() * 0.001;
      if (pos[i] < -5) pos[i] = 5;
    }
    mesh.current.geometry.attributes.position.needsUpdate = true;
  });

  return (
    <points ref={mesh}>
      <bufferGeometry>
        <bufferAttribute attach="attributes-position" array={positions} count={count} itemSize={3} />
      </bufferGeometry>
      <pointsMaterial size={0.05} color="white" transparent opacity={0.9} />
    </points>
  );
}
```

### Smooth scroll setup (Lenis + GSAP)
```jsx
import { ReactLenis } from "lenis/react";

<ReactLenis root options={{ duration: 2 }}>
  <main>{children}</main>
</ReactLenis>
```

### Preloader with GSAP progress
```jsx
function Preloader({ onComplete }) {
  const [progress, setProgress] = useState(0);
  useEffect(() => {
    gsap.to({ val: 0 }, {
      val: 100,
      duration: 2.5,
      ease: "power2.inOut",
      onUpdate: function () { setProgress(Math.round(this.targets()[0].val)); },
      onComplete,
    });
  }, []);
  return <div className="preloader">{progress}%</div>;
}
```

### Spline integration with object manipulation
```jsx
const Spline = React.lazy(() => import("@splinetool/react-spline"));

function Scene({ onLoad }) {
  const handleLoad = (splineApp) => {
    const obj = splineApp.findObjectByName("Keyboard");
    // Manipulate: obj.scale, obj.position, obj.rotation, obj.visible
    splineApp.addEventListener("mouseHover", handleHover);
    onLoad();
  };
  return (
    <Suspense fallback={<Loader />}>
      <Spline scene="/assets/scene.spline" onLoad={handleLoad} />
    </Suspense>
  );
}
```

### Interactive globe (Cobe)
```jsx
import createGlobe from "cobe";

useEffect(() => {
  const globe = createGlobe(canvasRef.current, {
    width: 800, height: 800,
    phi: 0, theta: 0.3,
    dark: 1, diffuse: 0.4,
    mapSamples: 16000, mapBrightness: 1.2,
    baseColor: [1, 1, 1],
    markerColor: [251/255, 100/255, 21/255],
    glowColor: [1.2, 1.2, 1.2],
    markers: [{ location: [14.5995, 120.9842], size: 0.03 }],
    onRender: (state) => { state.phi = r.get(); state.width = width * 2; },
  });
  return () => globe.destroy();
}, []);
```

## Advanced techniques (what separates good from award-winning)

### Custom GLSL shaders
Custom shaders are what separate good 3D sites from Awwwards winners. Default materials look generic — custom shaders create uniqueness:

- **Custom vertex shaders**: Mesh deformation (wave effects, morph targets, scroll-driven distortion)
- **Custom fragment shaders**: Stylized materials (gradients, noise textures, iridescence, holographic)
- **Noise-based motion**: Simplex/Perlin/Worley noise for organic, non-repetitive movement
- **ShaderMaterial vs RawShaderMaterial**: Use ShaderMaterial when you want Three.js uniforms/attributes injected; RawShaderMaterial for full control
- **Shader transitions**: Custom fragment shaders for page/section transitions (dissolve, morph, distortion wipe)

### FBO (Frame Buffer Object) particles
For massive particle counts (10k-100k+), compute positions on the GPU:
```
Pattern:
1. Create a DataTexture with initial particle positions
2. Use a ping-pong FBO setup (two render targets)
3. Run a simulation shader each frame that reads one FBO and writes to the other
4. Read the output FBO as position data in the display shader
Result: 100k+ particles at 60fps (impossible with CPU-based positions)
```

### Adaptive performance
Detect device capability and serve appropriate quality:
```
Strategy:
- Check GPU via renderer.getContext().getParameter(renderer.getContext().RENDERER)
- Check device pixel ratio, memory, core count
- Tier 1 (high-end desktop): full effects, high-res textures, max particles
- Tier 2 (mid-range): reduced post-processing, medium textures, fewer particles
- Tier 3 (mobile/low-end): no post-processing, baked materials, minimal particles, or image/video fallback
- Use R3F's frameloop="demand" to only render when something changes (saves battery)
```

### Baked lighting
For complex scenes where real-time lighting is too expensive:
- Bake lighting in Blender onto textures (lightmaps)
- Export with the model — zero runtime lighting cost
- Combine with one dynamic light for interactive highlights

## Awwwards-quality design principles

### What makes a 3D site award-worthy

1. **Purposeful 3D** — 3D enhances the story, not just decoration. Concept first, technology second.
2. **Seamless integration** — 3D and 2D elements feel like one design, not separate layers
3. **Choreographed motion** — Animations are sequenced, not random; every movement has intent
4. **Performance** — Silky smooth on target devices (60fps minimum). Performance IS a feature — fast smooth 3D beats heavy laggy 3D.
5. **Loading experience** — The wait is part of the experience (animated preloader, progressive reveal). Many Awwwards loaders are themselves award-worthy.
6. **Interactivity** — Users can influence the 3D (hover, scroll, click, drag) — it responds
7. **Sound design** — Optional but impactful ambient audio or interaction sounds (always with user opt-in toggle)
8. **Typography** — Bold, oversized type (often 8-15vw) that plays with 3D depth. Premium display fonts (Neue Montreal, Clash Display, PP Mori, Satoshi)
9. **Dark/moody aesthetic** — Most awarded 3D sites use dark themes for contrast and drama
10. **Attention to detail** — Custom cursors that react to 3D hover states, micro-interactions, smooth transitions between states
11. **At least one "wow moment"** in the first 5 seconds
12. **Strategic restraint** — Selective use of 3D for key moments often beats "3D everywhere"

### Common Awwwards 3D site categories

- **Portfolio/Agency showcases** — Interactive hero + scroll-driven project reveals
- **Product launches** — 3D product model with configurable options (Apple-style)
- **Brand experiences** — Full WebGL world with narrative-driven navigation
- **Interactive stories** — Scroll-driven camera movement through 3D environment
- **Generative art** — Particle systems, noise fields, procedural geometry

## References

- `../../references/playbooks/3d-web-development-playbook.md`
- `../../references/checklists/3d-web-development-checklist.md`
- `../../references/playbooks/ui-delivery-playbook.md`
- `../../references/checklists/ui-fidelity-checklist.md`
