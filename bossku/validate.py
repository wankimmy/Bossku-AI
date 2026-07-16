from __future__ import annotations

from pathlib import Path

from bossku.paths import repo_root
from bossku.skills import skills_dir, validate_skills


REQUIRED_FILES = (
    "AGENTS.md",
    "CLAUDE.md",
    "README.md",
    "pyproject.toml",
    "skills/aliases.json",
)

REQUIRED_AGENTS = (
    "orchestrator.md",
    "planner.md",
    "executor.md",
    "auditor.md",
    "final-reviewer.md",
)


def validate_repo(root: Path | None = None) -> list[str]:
    errors: list[str] = []
    r = repo_root(root)
    for rel in REQUIRED_FILES:
        if not (r / rel).is_file():
            errors.append(f"missing required file: {rel}")
    agents = r / "agents"
    if not agents.is_dir():
        errors.append("missing agents/")
    else:
        for name in REQUIRED_AGENTS:
            if not (agents / name).is_file():
                errors.append(f"missing agent contract: agents/{name}")
    try:
        skills_dir(r)
    except FileNotFoundError:
        errors.append("missing skills directory")
    errors.extend(validate_skills(r))
    legacy_product = ["app/artisan", "web/package.json", "docker-compose.yml"]
    for rel in legacy_product:
        if (r / rel).exists():
            errors.append(f"legacy product path still present: {rel}")
    return errors
