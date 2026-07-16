# Security pitfalls

Recurring traps in auth, data handling, and abuse — promote repo-specific variants to memory or new bullets here after they repeat.

## Authentication and session

- Treating “logged in” as “authorized” without checking resource-level permissions on every sensitive action.
- Session fixation or long-lived tokens without rotation after privilege changes.
- OAuth misconfiguration: wrong redirect URIs, missing state/nonce, accepting tokens from the wrong audience.

## Input and injection

- Validating shape in the UI only; trusting client-sent IDs, roles, or prices on the server.
- Building SQL, shell, or LDAP from string concatenation instead of parameterized APIs.
- Deserializing untrusted blobs (pickle, YAML `!!python/object`, Java native serialization) without strict typing.

## Secrets and configuration

- Secrets in env vars committed to repo history, screenshots, or CI logs.
- Default credentials left enabled in staging that mirror production patterns.
- Debug endpoints or admin routes reachable in production without network or auth gates.

## Abuse and trust boundaries

- Rate limits only on login, not on signup, password reset, or expensive read APIs (enumeration and DoS).
- Webhooks verified by shared secret in query string or without timestamp/signature replay protection.
- LLM or agent tools that can read/write arbitrary paths, call arbitrary URLs, or exfiltrate memory without allowlists.

## Privacy and compliance

- Logging full payloads (PII, tokens, health data) “for debugging.”
- Retaining data without a defined retention policy or lawful basis for the use case.
- Cross-border data flows assumed safe without checking residency and subprocessors.
