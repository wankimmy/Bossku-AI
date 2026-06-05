#!/usr/bin/env python3
"""BosskuAI risk detector for model escalation and audit depth."""
from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass

RISK_PATTERNS = {
    "auth": [r"\bauth\b", r"login", r"session", r"oauth", r"password", r"permission", r"role", r"rbac"],
    "payment": [r"payment", r"billing", r"subscription", r"refund", r"gateway", r"invoice", r"tokeni[sz]ation"],
    "security": [r"security", r"xss", r"csrf", r"sql injection", r"vulnerab", r"encrypt", r"decrypt"],
    "privacy": [r"privacy", r"personal data", r"pii", r"pdpa", r"gdpr", r"data retention"],
    "tenant_isolation": [r"tenant", r"organization", r"organisation", r"company", r"account.*showing.*rows", r"rows.*belong", r"other customer", r"another customer", r"another organization", r"different organization", r"cross[- ]?tenant", r"data leak"],
    "migration": [r"migration", r"schema", r"alter table", r"drop table", r"truncate", r"index"],
    "production": [r"production", r"deploy", r"release", r"rollback", r"hotfix", r"vps", r"server"],
    "data_loss": [r"delete", r"remove all", r"wipe", r"purge", r"overwrite", r"destructive"],
    "secrets": [r"api[_ -]?key", r"secret", r"token", r"\.env", r"credential", r"private key"],
    "multi_service_architecture": [r"microservice", r"multi-service", r"docker compose", r"queue", r"redis", r"s3", r"worker"],
    "legal_compliance": [r"legal", r"compliance", r"terms", r"policy", r"contract", r"ssm", r"tax"],
}


@dataclass
class RiskResult:
    level: str
    reasons: list[str]
    frontier_required: bool


def detect(text: str) -> RiskResult:
    lower = text.lower()
    reasons: list[str] = []
    for category, patterns in RISK_PATTERNS.items():
        for pattern in patterns:
            if re.search(pattern, lower):
                reasons.append(category)
                break
    unique = sorted(set(reasons))
    if len(unique) >= 2 or any(r in unique for r in ["payment", "security", "secrets", "data_loss", "privacy", "tenant_isolation"]):
        return RiskResult("high", unique, True)
    if unique:
        return RiskResult("medium", unique, False)
    return RiskResult("low", [], False)


def main() -> int:
    parser = argparse.ArgumentParser(description="Detect BosskuAI task risk.")
    parser.add_argument("text", nargs="*", help="Task text. If omitted, stdin is used.")
    args = parser.parse_args()
    text = " ".join(args.text).strip() or sys.stdin.read()
    result = detect(text)
    print(json.dumps(result.__dict__, indent=2, sort_keys=True))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
