from __future__ import annotations

import os
from pathlib import Path


MANAGED_SKILL_PREFIX = "bosskuai-"
COFOUNDER_SKILL = "cofounder"
MARKER_START = "<!-- bosskuai:start -->"
MARKER_END = "<!-- bosskuai:end -->"


def repo_root(explicit: Path | None = None) -> Path:
    if explicit is not None:
        return explicit.resolve()
    env = os.environ.get("BOSSKU_ROOT")
    if env:
        return Path(env).resolve()
    here = Path(__file__).resolve().parent.parent
    if (here / "skills").is_dir() or (here / "AGENTS.md").is_file():
        return here
    raise FileNotFoundError("BosskuAI repo root not found; set BOSSKU_ROOT")


def user_config_dir(home: Path | None = None) -> Path:
    base = home if home is not None else Path.home()
    return base / ".bosskuai"


def user_config_path(home: Path | None = None) -> Path:
    return user_config_dir(home) / "config.json"


def agents_skills_dir(home: Path | None = None) -> Path:
    base = home if home is not None else Path.home()
    return base / ".agents" / "skills"


def claude_skills_dir(home: Path | None = None) -> Path:
    base = home if home is not None else Path.home()
    return base / ".claude" / "skills"


def project_meta_dir(project: Path) -> Path:
    return project.resolve() / ".bossku"


def project_memory_dir(project: Path) -> Path:
    return project_meta_dir(project) / "memory"


def vault_export_dir(vault: Path, project_name: str) -> Path:
    safe = _safe_segment(project_name)
    return vault.resolve() / "BosskuAI" / safe


def _safe_segment(name: str) -> str:
    cleaned = "".join(c if c.isalnum() or c in "-_" else "-" for c in name.strip())
    return cleaned or "project"


def ensure_inside(child: Path, parent: Path) -> Path:
    child_r = child.resolve()
    parent_r = parent.resolve()
    if parent_r not in child_r.parents and child_r != parent_r:
        raise ValueError(f"path escapes allowed directory: {child}")
    return child_r
