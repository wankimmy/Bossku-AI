from __future__ import annotations

from pathlib import Path

from bossku.memory import load_user_config
from bossku.paths import MARKER_START, agents_skills_dir, claude_skills_dir, repo_root
from bossku.skills import count_managed_skills, validate_skills
from bossku.validate import claude_imports_agents_md


def gather_doctor_issues(
    root: Path | None = None,
    home: Path | None = None,
    *,
    project: Path | None = None,
) -> list[str]:
    issues: list[str] = []
    try:
        r = repo_root(root)
    except FileNotFoundError as exc:
        issues.append(str(exc))
        r = None
    else:
        issues.extend(validate_skills(r))

    cfg = load_user_config(home)
    if not cfg.get("installed_from"):
        issues.append("user install not detected; run `bossku install`")
    else:
        h = home if home is not None else Path.home()
        agents_path = agents_skills_dir(h)
        claude_path = claude_skills_dir(h)
        for label, path in (
            ("~/.agents/skills", agents_path),
            ("~/.claude/skills", claude_path),
        ):
            if not path.is_dir():
                issues.append(f"missing skill directory {label}; run `bossku install`")
        if r is not None and agents_path.is_dir() and claude_path.is_dir():
            agents_n = count_managed_skills(agents_path, r)
            claude_n = count_managed_skills(claude_path, r)
            if agents_n == 0:
                issues.append("no managed skills in ~/.agents/skills; run `bossku install`")
            if claude_n == 0:
                issues.append("no managed skills in ~/.claude/skills; run `bossku install`")
            if agents_n > 0 and claude_n > 0 and agents_n != claude_n:
                issues.append(
                    f"skill mirror mismatch: agents={agents_n} claude={claude_n}; run `bossku update`"
                )

    if project is not None:
        proj = project.resolve()
        agents_file = proj / "AGENTS.md"
        claude_file = proj / "CLAUDE.md"
        if not agents_file.is_file():
            issues.append(f"missing project AGENTS.md at {proj}; run `bossku init`")
        elif MARKER_START not in agents_file.read_text(encoding="utf-8"):
            issues.append(f"project AGENTS.md missing BosskuAI managed block; run `bossku init {proj}`")
        if not claude_file.is_file():
            issues.append(f"missing project CLAUDE.md at {proj}; run `bossku init`")
        elif not claude_imports_agents_md(claude_file.read_text(encoding="utf-8")):
            issues.append(
                f"project CLAUDE.md must include bare @AGENTS.md; run `bossku init {proj}`"
            )

    return issues


def format_doctor_success(
    root: Path | None,
    home: Path | None,
    *,
    version: str,
) -> list[str]:
    h = home if home is not None else Path.home()
    r = repo_root(root)
    agents_path = agents_skills_dir(h)
    claude_path = claude_skills_dir(h)
    n = count_managed_skills(agents_path, r)
    lines = [f"doctor: ok (bossku {version})"]
    lines.append(
        f"  cursor, codex, opencode: {agents_path} ({n} managed skills)"
    )
    lines.append(f"  claude_code: {claude_path} ({n} managed skills)")
    lines.append("  project instructions: run `bossku init <project>` for AGENTS.md + CLAUDE.md")
    return lines
