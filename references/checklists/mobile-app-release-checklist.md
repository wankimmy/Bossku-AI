# Mobile App Release Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Does the company (not an individual) own the Apple Developer and Google Play accounts, and are signing credentials in EAS or the CI secret store?
- Is crash-free rate ≥ 99.5% on the beta track with no ANRs on the test devices?
- Is in-app account deletion available, and is the privacy policy URL live and reachable without login?
- Does every declared permission have a purpose string and an in-context request?
- Is Sign in with Apple offered if any other social login exists?
- Are digital goods sold only through IAP / Play Billing with disclosed subscription terms?
- Are demo credentials and review notes prepared for login-gated apps?
- Are Data safety (Google) and privacy nutrition labels (Apple) filled from an enumeration of SDKs and endpoints?
- Are target API level, content rating, and export compliance answered?
- Were deep links, push notifications, and payments tested on physical iOS and Android devices with production credentials?
- Are version, build number / `versionCode`, changelog, and screenshots updated, and is the build number strictly increasing?
- Is crash reporting tagged with the release and are source maps uploaded?
- Is the rollout phased (Apple) / staged (Google) with halt criteria and watch metrics defined?
- Is the rollback path decided: OTA republish for JS-only, resubmit for native?
- Is the OTA channel mapped to the right `runtimeVersion`, and is the previous update kept for rollback?
- Is someone watching crash rate, ANR rate, reviews, and backend errors for the first 48 hours?
