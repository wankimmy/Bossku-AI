from __future__ import annotations

import json
import shutil
from pathlib import Path

from bossku.memory import init_memory_templates
from bossku.paths import MARKER_END, MARKER_START, project_meta_dir
from bossku.skills import copy_skills_to, skills_dir


def _managed_block(content: str) -> str:
    return f"{MARKER_START}\n{content.strip()}\n{MARKER_END}"


def upsert_managed_block(existing: str, block: str) -> str:
    wrapped = _managed_block(block)
    if MARKER_START in existing and MARKER_END in existing:
        start = existing.index(MARKER_START)
        end = existing.index(MARKER_END) + len(MARKER_END)
        return existing[:start] + wrapped + existing[end:]
    if existing.strip():
        return existing.rstrip() + "\n\n" + wrapped + "\n"
    return wrapped + "\n"


def init_project(
    project: Path,
    *,
    root: Path | None = None,
    portable: bool = False,
    profile: str = "core",
) -> dict:
    project = project.resolve()
    project.mkdir(parents=True, exist_ok=True)
    meta = project_meta_dir(project)
    meta.mkdir(parents=True, exist_ok=True)
    meta_file = meta / "project.json"
    if not meta_file.exists():
        meta_file.write_text(
            json.dumps({"bossku_version": "2.0.0", "profile": profile}, indent=2),
            encoding="utf-8",
        )
    init_memory_templates(project)
    agents_path = project / "AGENTS.md"
    claude_path = project / "CLAUDE.md"
    block = (
        "BosskuAI co-founder mode is active. Read the global skill library and follow "
        "plan → execute → audit. Save durable decisions with `bossku remember`."
    )
    if agents_path.exists():
        agents_path.write_text(
            upsert_managed_block(agents_path.read_text(encoding="utf-8"), block),
            encoding="utf-8",
        )
    else:
        agents_path.write_text(
            "# Project Agents\n\n" + _managed_block(block) + "\n",
            encoding="utf-8",
        )
    claude_stub = "@AGENTS.md\n"
    if claude_path.exists():
        text = claude_path.read_text(encoding="utf-8")
        if "@AGENTS.md" not in text:
            claude_path.write_text(claude_stub + "\n" + text, encoding="utf-8")
    else:
        claude_path.write_text(claude_stub, encoding="utf-8")
    portable_info = None
    if portable:
        dest = project / ".bossku" / "skills"
        copy_skills_to(dest, root, profile)
        portable_info = str(dest)
    return {
        "project": str(project),
        "meta": str(meta_file),
        "memory": str(meta / "memory"),
        "portable_skills": portable_info,
    }
