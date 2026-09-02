---
name: bosskuai-mobile-app-release
description: "Use this for shipping a mobile app to the Apple App Store and Google Play — release readiness, signing credentials, EAS Build/Submit or Fastlane, TestFlight and Play testing tracks, review guidelines and common rejections, privacy nutrition labels and Data safety forms, phased and staged rollouts, OTA update policy, versioning, crash monitoring, and launch-day operations. Store listing copy and keywords belong to the vendored aso skill; building the app to bosskuai-expo-react-native."
---

# BosskuAI Mobile App Release

Use this skill when an app must reach the App Store or Google Play, or when an existing release process needs to be made repeatable and safe.

## How this differs from nearby skills

- **`aso`** (marketingskills): listing copy, screenshots, keywords, conversion; this skill gets the binary approved and rolled out.
- **`bosskuai-expo-react-native`**: builds the app; this skill ships it.
- **`bosskuai-ci-cd-pipelines`**: generic pipeline design; this skill defines the mobile-specific stages and gates.
- **`bosskuai-launch-commercialization`**: go-to-market; this skill is the engineering release path inside that plan.

## Mindset

- Store review is a gate you cannot hotfix around; plan for 24–72 hours and for one rejection.
- Every release needs a rollback path before it ships: halt the rollout, or push a JS-only fix, or submit a fixed build.
- The first version's privacy answers, bundle id, and account ownership are expensive to change. Get them right once.
- The company owns the developer accounts and signing keys, never an individual's Apple ID.

## Release readiness gate (P0 before submission)

- Crash-free sessions ≥ 99.5% on the beta track; no ANRs on the test devices; cold start under ~2s on a mid-range Android.
- In-app account deletion for any app with sign-up (Apple 5.1.1(v), Google account deletion policy); privacy policy URL live.
- Every permission has a purpose string and is requested in context; no unused permissions declared.
- Sign in with Apple offered if any third-party social login is offered.
- Digital goods sold only through IAP / Play Billing; subscription terms and price disclosed on the paywall.
- Demo account and review notes prepared when login is required.
- Deep links, push notifications, and payments tested on physical devices with production credentials.
- Crash reporting (Sentry/Crashlytics) and analytics tagged with the release version; source maps uploaded.
- Version bumped, build number monotonic, changelog written, screenshots current.

## Credentials and signing

- Apple: Distribution certificate + App Store provisioning profile, App Store Connect API key for CI; store in EAS credentials or the CI secret store.
- Google: upload key (Play App Signing holds the app signing key), service account JSON with release permission scoped to the app.
- Never in the repo, never in chat; rotate on team changes; document who can restore access.

## Pipeline

1. Release branch or tag → CI builds with the production profile (`eas build --profile production` or Fastlane `gym`/`gradle`), build number auto-incremented.
2. Auto-submit (`eas submit`, Fastlane `pilot`/`supply`) to TestFlight and the Play internal track.
3. QA sign-off on the beta build against the readiness gate; regression on the device matrix.
4. Submit for review with notes and demo credentials; Google: complete Data safety, target API, content rating.
5. Release: Apple phased release (7 days, pausable) and Play staged rollout (5% → 20% → 50% → 100%, haltable).
6. Watch for 24–48h: crash rate, ANR rate, 1-star review spikes, backend error rates from the new version.

## Common rejections and how to avoid them

- Apple 2.1 (crashes/incomplete), 2.3 (metadata mismatch), 4.2 (minimum functionality: a wrapped website), 5.1.1 (data collection without purpose), 3.1.1 (external payment for digital goods), missing demo account, login walls without value preview.
- Google: Data safety form contradicting SDK behavior, missing target API level, sensitive permission declarations (SMS, call log, background location) without a form, deceptive behavior, broken links in the listing.
- Both: age rating answers, export compliance (encryption) declaration, privacy policy reachable without login.

## Rollout, OTA, and rollback

- OTA via EAS Update only for JS/asset changes and only to builds with the same `runtimeVersion`; map channels to production/preview; keep the previous update to republish as rollback.
- Native changes (new modules, permissions, SDK upgrades) always need a store build.
- Halting a staged rollout stops new installs, not existing ones; a bad build already at 100% needs a fixed build fast-tracked with an expedited review request (Apple) and a full rollout (Google).
- Watch Play Core Vitals thresholds (user-perceived crash ~1.09%, ANR ~0.47%); breaching them hurts discoverability.

## Versioning

- Marketing version follows semver; iOS build number and Android `versionCode` strictly increase and never reuse.
- `runtimeVersion` policy: `appVersion` when every native change ships with a version bump, `fingerprint` when native drift must be detected automatically.

## Guardrails

- Do not submit a build that has not run on physical iOS and Android devices.
- Do not change the bundle id or package name after the first release.
- Do not ship native changes through OTA.
- Do not answer privacy forms from memory; enumerate SDKs and endpoints first.
- Do not keep signing material outside the CI secret store or EAS credentials.

## Output format

```text
Release: [app, version, build] - Platforms: [iOS/Android] - Pipeline: [EAS | Fastlane]
Readiness gate: [pass | blockers listed]
Credentials: [where stored, owner]
Rollout plan: [phased/staged steps, watch metrics, halt criteria]
Rollback: [OTA republish | build resubmit | halt]
Review notes prepared: [yes/no, demo account]
```

## References

- `../../references/checklists/mobile-app-release-checklist.md`
- `../../references/checklists/expo-react-native-checklist.md`
- `../../references/checklists/launch-commercialization-checklist.md`
