from __future__ import annotations

import json
import shutil
from pathlib import Path

from bossku import __version__
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
    metadata = json.loads(meta_file.read_text(encoding="utf-8")) if meta_file.exists() else {}
    metadata["bossku_version"] = __version__
    metadata.setdefault("profile", profile)
    meta_file.write_text(json.dumps(metadata, indent=2), encoding="utf-8")
    init_memory_templates(project)
    agents_path = project / "AGENTS.md"
    claude_path = project / "CLAUDE.md"
    omp_dir = project / ".omp"
    omp_agents_path = omp_dir / "AGENTS.md"
    omp_config_path = omp_dir / "config.yml"
    block = (
        "BosskuAI is active. Before multi-step work, match the task to an installed skill "
        "(use `bossku skills find` when unclear). Select one primary skill and the smallest "
        "complementary set justified by distinct prompt concerns; multiple skills are valid. "
        "Use Superpowers for process, Anti-Slop for output quality, and verify before completion. "
        "Save durable decisions with `bossku remember`. "
        "Grounding is always on: say when evidence is insufficient instead of guessing, "
        "and ground factual claims in quotes, file:line, or command output."
        # antislop runs an install wizard and a blocking "during or after?" question unless the
        # entry file carries its pointer block; Bossku already installs all six skills.
        "\n<!-- antislop:start -->\n"
        "antislop: Bossku installs all six antislop skills; skip the install wizard. "
        "Mode: during the work for new UI, after it for audits of existing UI, unless the user says otherwise.\n"
        "<!-- antislop:end -->"
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
    omp_dir.mkdir(parents=True, exist_ok=True)
    omp_stub = "@../AGENTS.md\n"
    if omp_agents_path.exists():
        text = omp_agents_path.read_text(encoding="utf-8")
        has_omp_import = any(
            line.strip() == "@../AGENTS.md" and "`" not in line
            for line in text.splitlines()
        )
        if not has_omp_import:
            omp_agents_path.write_text(omp_stub + "\n" + text, encoding="utf-8")
    else:
        omp_agents_path.write_text(omp_stub, encoding="utf-8")
    if not omp_config_path.exists():
        omp_config_path.write_text(
            "tools:\n  approvalMode: write\n",
            encoding="utf-8",
        )
    portable_info = None
    if portable:
        dest = project / ".bossku" / "skills"
        copy_skills_to(dest, root, profile)
        portable_info = str(dest)
    return {
        "project": str(project),
        "meta": str(meta_file),
        "memory": str(meta / "memory"),
        "omp": str(omp_dir),
        "portable_skills": portable_info,
    }
