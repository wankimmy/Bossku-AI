# Expo / React Native Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Do `npx expo-doctor` and `npx expo install --check` pass on the target SDK?
- Is the workflow correct for the native needs: Expo Go, development build, or bare, and are config plugins used instead of ejecting?
- Is `runtimeVersion` policy set, and does every native change ship with a new build rather than an OTA update?
- Are long lists rendered with FlashList/FlatList with stable keys, not a ScrollView?
- Do animations run in Reanimated worklets and is `GestureHandlerRootView` at the root?
- Are safe-area insets used instead of hard-coded padding, and is keyboard behavior tested per platform?
- Is the Android hardware back button handled?
- Are tokens in SecureStore and is nothing sensitive in `EXPO_PUBLIC_*` or AsyncStorage?
- Do deep links work from cold start and warm start on both platforms (scheme, Universal Links, App Links)?
- Are notification permissions requested in context and tested on physical devices with production credentials?
- Are images sized explicitly and cached with `expo-image`?
- Is offline behavior defined (cached queries, queued mutations, visible offline state)?
- Are crash reporting and source maps wired per build?
- Was a release build profiled on a mid-range Android device and the smallest supported iPhone?
- Do unit tests (jest-expo) and at least one Maestro/Detox flow pass on a built binary?
- Are `targetSdkVersion`, Xcode SDK, and privacy manifests current for the store deadlines?
