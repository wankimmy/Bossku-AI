---
name: bosskuai-expo-react-native
description: "Use this for React Native and Expo work — Expo SDK and Expo Router, EAS Build and EAS Update, development builds vs Expo Go, config plugins and native modules, navigation, offline storage, push notifications, deep links, permissions, on-device performance, and iOS vs Android differences. Store submission and release belong to bosskuai-mobile-app-release; web React to bosskuai-react-development."
---

# BosskuAI Expo / React Native

Use this skill when building, auditing, or debugging a React Native app, with or without Expo, where the answer depends on native platform behavior rather than on React alone.

## How this differs from nearby skills

- **`bosskuai-react-development`**: React on the web; hooks and state advice carry over, DOM and routing advice does not.
- **`bosskuai-mobile-app-release`**: credentials, store review, rollouts, and OTA policy; this skill gets the app correct on device.
- **`bosskuai-api-design`** / backend skills: the API the app calls.
- **`animate`** (emil-skills): motion decisions; this skill implements them with Reanimated and Gesture Handler.

## Mindset

- The device is the truth. Simulators hide permission prompts, memory pressure, slow networks, and real keyboards.
- Two threads: JS and UI. Anything that blocks JS drops frames; anything heavy belongs in worklets, native modules, or off the render path.
- Stay on managed Expo until a real native need appears; then add a config plugin or a development build, not a bare eject.
- Every permission is a product decision with a purpose string and a timing.

## Orient before changing anything

1. `npx expo-doctor` and `npx expo install --check`: SDK version, mismatched native dependencies.
2. `app.json` / `app.config.ts`: bundle id / package, scheme, permissions, plugins, `runtimeVersion` policy, New Architecture flag, Hermes.
3. `eas.json`: build profiles (development, preview, production), channels, env per profile.
4. Navigation: Expo Router (file-based, groups, layouts) or React Navigation; auth gating approach.
5. Data and storage: TanStack Query, MMKV, SQLite/expo-sqlite, SecureStore; offline expectations.
6. Expo Go vs development build: any custom native module, notification, or in-app purchase means a development build.

## Rules that catch most React Native bugs

- Long lists: `FlashList` or `FlatList` with stable keys, `getItemLayout` where sizes are fixed, never a `ScrollView` of hundreds of children.
- Inline objects and functions on list items re-render every row; memoize row components and pass primitives.
- Reanimated: animation math in worklets (`useAnimatedStyle`, `useSharedValue`); never read shared values on the JS thread inside render.
- Gesture Handler needs `GestureHandlerRootView` at the root; nested gestures need explicit `simultaneousWithExternalGesture`.
- Safe areas via `react-native-safe-area-context` insets, not hard-coded padding; notch, home indicator, and Android navigation bar differ.
- Keyboard: `KeyboardAvoidingView` `behavior` differs per platform; test with a real keyboard and a small screen.
- Android hardware back: handle it in Expo Router or `BackHandler`; do not trap the user.
- Text must be inside `<Text>`; layout uses flexbox with `flexDirection: 'column'` default.
- Images: `expo-image` with explicit sizes and caching; never decode large images on the JS side.
- Secrets: `SecureStore` for tokens; `AsyncStorage`/MMKV only for non-sensitive data; `EXPO_PUBLIC_*` env is bundled into the app and is public by definition.
- Networking: iOS ATS blocks plain HTTP; Android 9+ blocks cleartext unless configured. Retry with backoff; show offline state.
- Deep links: `scheme` for custom links, Universal Links (AASA) and App Links (assetlinks.json) for HTTPS; test cold start and warm start paths.
- Notifications: request permission in context, not at launch; test on physical devices with production credentials; Android needs a channel.
- OTA (EAS Update): only JS and assets; any native change (new module, permission, SDK upgrade) requires a new build and a new `runtimeVersion`.
- New Architecture: check each native library's compatibility before enabling; Fabric/TurboModules break old bridge-based modules.
- Platform targets: Play requires a recent `targetSdkVersion` each year; Apple requires current Xcode SDK and privacy manifests for listed APIs.

## Architecture that holds up

- Expo Router route groups: `(auth)`, `(app)`; a root layout that redirects on session state; typed routes on.
- Server state in TanStack Query persisted to MMKV for instant cold-start UI; mutations queued offline where the product needs it.
- Feature folders (`features/<name>/{components,hooks,api,schema}`) with zod schemas shared with the backend when possible.
- Theming through a single token module; i18n with `expo-localization` + i18next or Lingui from day one if more than one language is plausible.
- Crash and error reporting (Sentry) with source maps uploaded per build.

## Performance on device

- Profile release builds on a mid-range Android device, not the iOS simulator.
- Startup: lazy-require heavy screens, avoid top-level work in modules, measure with `expo-atlas` for bundle composition.
- Frames: React DevTools / Flipper / Perf Monitor; move heavy work to worklets or native; avoid bridging in loops.
- Memory: unsubscribe listeners on unmount, cap image cache, page data.

## Testing

- `jest-expo` + Testing Library for React Native for components and hooks.
- Maestro (or Detox) flows on real devices or emulators for critical paths; run on EAS-built binaries in CI.
- Manual matrix: smallest supported iPhone, a mid-range Android, both OS minimum versions, dark mode, large font sizes.

## Verification

```bash
npx expo-doctor && npx expo install --check
tsc --noEmit && eslint .
jest
eas build --profile preview --platform all   # then install on devices
```

## Guardrails

- Do not eject to bare workflow to solve something a config plugin or development build already solves.
- Do not ship native changes through EAS Update.
- Do not store tokens in AsyncStorage or ship secrets in `EXPO_PUBLIC_*`.
- Do not test only on simulators before a release candidate.
- Do not enable the New Architecture without checking every native dependency.

## Output format

```text
Expo SDK: [xx] - RN: [x.y] - Workflow: [managed | dev build | bare] - New Arch: [on/off]
Router: [Expo Router | React Navigation] - Data: [...] - Storage: [...]

Findings:
  P0/P1/P2 - [file:line or config key] - [issue] - [fix]

Change plan: [smallest correct change]
Device verification: [devices/OS versions tested, results]
```

## References

- `../../references/checklists/expo-react-native-checklist.md`
- `../../references/checklists/react-development-checklist.md`
- `../../references/checklists/mobile-app-release-checklist.md`
