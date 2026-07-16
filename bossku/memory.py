from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

from bossku.paths import ensure_inside, project_memory_dir, user_config_path, vault_export_dir
from bossku.redact import redact

ALLOWED_KINDS = frozenset({"decision", "plan", "learning", "project"})
KIND_TO_FILE = {
    "decision": "decisions.md",
    "plan": "plans.md",
    "learning": "learnings.md",
    "project": "project.md",
}


def load_user_config(home: Path | None = None) -> dict:
    path = user_config_path(home)
    if not path.is_file():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def save_user_config(data: dict, home: Path | None = None) -> None:
    path = user_config_path(home)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2), encoding="utf-8")


def remember(
    project: Path,
    kind: str,
    note: str,
    *,
    home: Path | None = None,
) -> dict:
    if kind not in ALLOWED_KINDS:
        raise ValueError(f"kind must be one of {sorted(ALLOWED_KINDS)}")
    cleaned = redact(note.strip())
    if not cleaned:
        raise ValueError("note cannot be empty after redaction")
    mem_dir = project_memory_dir(project)
    mem_dir.mkdir(parents=True, exist_ok=True)
    target = mem_dir / KIND_TO_FILE[kind]
    stamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    entry = f"\n## {stamp}\n\n{cleaned}\n"
    if target.exists():
        target.write_text(target.read_text(encoding="utf-8") + entry, encoding="utf-8")
    else:
        target.write_text(f"# {kind.title()}s\n{entry}", encoding="utf-8")
    sync_state = _load_sync_state(project)
    sync_state.setdefault("pending", []).append(kind)
    _save_sync_state(project, sync_state)
    vault_result = sync_project(project, home=home)
    return {"kind": kind, "file": str(target), "vault": vault_result}


def sync_project(project: Path, *, home: Path | None = None) -> dict:
    cfg = load_user_config(home)
    vault_path = cfg.get("obsidian_vault")
    if not vault_path:
        return {"status": "skipped", "reason": "no vault configured"}
    vault = Path(vault_path)
    if not vault.is_dir():
        return {"status": "pending", "reason": "vault unavailable"}
    project_name = project.resolve().name
    export_base = vault_export_dir(vault, project_name)
    ensure_inside(export_base, vault)
    export_base.mkdir(parents=True, exist_ok=True)
    mem_dir = project_memory_dir(project)
    exported: list[str] = []
    conflicts: list[str] = []
    state = _load_sync_state(project)
    file_hashes: dict[str, str] = state.get("file_hashes", {})
    for kind, filename in KIND_TO_FILE.items():
        src = mem_dir / filename
        if not src.is_file():
            continue
        content = src.read_text(encoding="utf-8")
        digest = hashlib.sha256(content.encode("utf-8")).hexdigest()
        dest = export_base / filename
        ensure_inside(dest, vault)
        if dest.is_file():
            prev = file_hashes.get(filename)
            current_vault = hashlib.sha256(dest.read_bytes()).hexdigest()
            if prev and current_vault != prev and digest != current_vault:
                conflict = export_base / f"{filename}.conflict.md"
                conflict.write_text(dest.read_text(encoding="utf-8"), encoding="utf-8")
                conflicts.append(str(conflict))
        dest.write_text(content, encoding="utf-8")
        file_hashes[filename] = digest
        exported.append(filename)
    state["file_hashes"] = file_hashes
    state["pending"] = []
    state["last_sync"] = datetime.now(timezone.utc).isoformat()
    _save_sync_state(project, state)
    return {"status": "ok", "exported": exported, "conflicts": conflicts, "vault_dir": str(export_base)}


def _sync_state_path(project: Path) -> Path:
    return project_memory_dir(project) / "sync-state.json"


def _load_sync_state(project: Path) -> dict:
    path = _sync_state_path(project)
    if not path.is_file():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def _save_sync_state(project: Path, state: dict) -> None:
    path = _sync_state_path(project)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, indent=2), encoding="utf-8")


def init_memory_templates(project: Path) -> None:
    mem = project_memory_dir(project)
    mem.mkdir(parents=True, exist_ok=True)
    templates = {
        "project.md": "# Project\n\nOne-paragraph summary of what this repo is and who it serves.\n",
        "decisions.md": "# Decisions\n\nDurable decisions only.\n",
        "plans.md": "# Plans\n\nActive and recent plans.\n",
        "learnings.md": "# Learnings\n\nVerified lessons worth repeating.\n",
        "handoff.md": "# Handoff\n\nEphemeral continuation state. Clear when done.\n",
    }
    for name, body in templates.items():
        path = mem / name
        if not path.exists():
            path.write_text(body, encoding="utf-8")
