from __future__ import annotations

import re

SENSITIVE_PATTERNS = [
    re.compile(r"(?i)(api[_-]?key|secret|token|password|bearer)\s*[:=]\s*\S+"),
    re.compile(r"(?i)-----BEGIN (?:RSA |EC )?PRIVATE KEY-----"),
    re.compile(r"sk-[a-zA-Z0-9]{20,}"),
    re.compile(r"ghp_[a-zA-Z0-9]{20,}"),
]


def redact(text: str) -> str:
    out = text
    for pattern in SENSITIVE_PATTERNS:
        out = pattern.sub("[REDACTED]", out)
    return out
