from __future__ import annotations

import json
import shutil
from pathlib import Path

from bossku.paths import (
    agents_skills_dir,
    claude_skills_dir,
    repo_root,
    user_config_dir,
)
from bossku.skills import copy_skills_to, is_managed_skill_name, write_routing_cache


def install_user(
    *,
    root: Path | None = None,
    home: Path | None = None,
    profile: str = "full",
    vault: str | None = None,
) -> dict:
    r = repo_root(root)
    h = home if home is not None else Path.home()
    agents_dest = agents_skills_dir(h)
    claude_dest = claude_skills_dir(h)
    installed_agents = copy_skills_to(agents_dest, r, profile)
    installed_claude = copy_skills_to(claude_dest, r, profile)
    cache_path = user_config_dir(h) / "routing-cache.json"
    write_routing_cache(cache_path, r)
    cfg_path = user_config_dir(h) / "config.json"
    cfg: dict = {}
    if cfg_path.is_file():
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
    cfg["installed_from"] = str(r)
    cfg["profile"] = profile
    if vault:
        cfg["obsidian_vault"] = vault
    cfg_path.parent.mkdir(parents=True, exist_ok=True)
    cfg_path.write_text(json.dumps(cfg, indent=2), encoding="utf-8")
    return {
        "agents_skills": str(agents_dest),
        "claude_skills": str(claude_dest),
        "installed_count": len(installed_agents),
        "routing_cache": str(cache_path),
    }


def update_user(*, root: Path | None = None, home: Path | None = None) -> dict:
    cfg_path = user_config_dir(home) / "config.json"
    profile = "full"
    vault = None
    if cfg_path.is_file():
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
        profile = cfg.get("profile", "full")
        vault = cfg.get("obsidian_vault")
    return install_user(root=root, home=home, profile=profile, vault=vault)


def uninstall_user(*, root: Path | None = None, home: Path | None = None, purge: bool = False) -> dict:
    h = home if home is not None else Path.home()
    cfg_path = user_config_dir(h) / "config.json"
    effective_root = root
    if effective_root is None and cfg_path.is_file():
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
        installed_from = cfg.get("installed_from")
        if installed_from:
            effective_root = Path(installed_from)
    removed: list[str] = []
    for dest in (agents_skills_dir(h), claude_skills_dir(h)):
        if not dest.is_dir():
            continue
        for child in list(dest.iterdir()):
            if child.is_dir() and is_managed_skill_name(child.name, effective_root):
                shutil.rmtree(child)
                removed.append(child.name)
    if purge and cfg_path.is_file():
        cfg_path.unlink()
    cache = user_config_dir(h) / "routing-cache.json"
    if purge and cache.is_file():
        cache.unlink()
    return {"removed_skills": removed, "purged_config": purge}
