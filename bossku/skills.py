from __future__ import annotations

import json
import re
import shutil
from dataclasses import dataclass
from pathlib import Path

from bossku.paths import COFOUNDER_SKILL, MANAGED_SKILL_PREFIX, repo_root


@dataclass
class SkillMeta:
    skill_id: str
    name: str
    description: str
    triggers: list[str]
    keywords: list[str]
    path: Path


def aliases_path(root: Path | None = None) -> Path:
    return repo_root(root) / "skills" / "aliases.json"


def vendored_path(root: Path | None = None) -> Path:
    return repo_root(root) / "skills" / "vendored.json"


def load_vendored(root: Path | None = None) -> dict[str, str]:
    path = vendored_path(root)
    if not path.is_file():
        return {}
    data = json.loads(path.read_text(encoding="utf-8"))
    skills = data.get("skills", {})
    return {str(k): str(v) for k, v in skills.items()}


def load_vendored_ids(root: Path | None = None) -> set[str]:
    return set(load_vendored(root).keys())


def load_aliases(root: Path | None = None) -> dict[str, str]:
    path = aliases_path(root)
    if not path.is_file():
        return {}
    data = json.loads(path.read_text(encoding="utf-8"))
    return {k: v for k, v in data.get("aliases", {}).items()}


def skills_dir(root: Path | None = None) -> Path:
    r = repo_root(root)
    if (r / "skills").is_dir():
        return r / "skills"
    legacy = r / "ai-assistant" / "skills"
    if legacy.is_dir():
        return legacy
    raise FileNotFoundError("skills directory not found")


def list_skill_ids(root: Path | None = None) -> list[str]:
    base = skills_dir(root)
    aliases = set(load_aliases(root).keys())
    ids: list[str] = []
    for child in sorted(base.iterdir()):
        if not child.is_dir():
            continue
        if not (child / "SKILL.md").is_file():
            continue
        sid = child.name
        if sid in aliases:
            continue
        ids.append(sid)
    return ids


def parse_skill_md(path: Path) -> SkillMeta:
    text = path.read_text(encoding="utf-8")
    front = _parse_frontmatter(text)
    skill_id = path.parent.name
    triggers = front.get("triggers", [])
    keywords = front.get("keywords", [])
    if isinstance(triggers, str):
        triggers = [triggers]
    if isinstance(keywords, str):
        keywords = [keywords]
    return SkillMeta(
        skill_id=skill_id,
        name=str(front.get("name", skill_id)),
        description=str(front.get("description", "")),
        triggers=[str(t) for t in triggers],
        keywords=[str(k) for k in keywords],
        path=path.parent,
    )


def _parse_frontmatter(text: str) -> dict:
    if not text.startswith("---"):
        return {}
    end = text.find("---", 3)
    if end == -1:
        return {}
    block = text[3:end].strip()
    out: dict[str, object] = {}
    key: str | None = None
    lines = block.splitlines()
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()
        if stripped.startswith("- ") and key:
            items = out.setdefault(key, [])
            if isinstance(items, list):
                items.append(stripped[2:].strip())
            i += 1
            continue
        if ":" in line and (not line or not line[0].isspace()):
            key_part, val = line.split(":", 1)
            key = key_part.strip()
            val = val.strip()
            if val in (">", "|"):
                folded = val == ">"
                i += 1
                parts: list[str] = []
                while i < len(lines):
                    nxt = lines[i]
                    if nxt.strip() == "":
                        if not folded:
                            parts.append("")
                        i += 1
                        continue
                    if not nxt[0].isspace():
                        head = nxt.split(":", 1)[0].strip()
                        if head and re.match(r"^[A-Za-z0-9_-]+$", head):
                            break
                    parts.append(nxt.strip())
                    i += 1
                out[key] = (" ".join(parts) if folded else "\n".join(parts)).strip()
                continue
            if val.startswith("[") and val.endswith("]"):
                inner = val[1:-1]
                out[key] = [p.strip().strip("'\"") for p in inner.split(",") if p.strip()]
            else:
                out[key] = val.strip("'\"")
            i += 1
            continue
        i += 1
    return out


def load_all_skills(root: Path | None = None) -> dict[str, SkillMeta]:
    base = skills_dir(root)
    skills: dict[str, SkillMeta] = {}
    for sid in list_skill_ids(root):
        meta = parse_skill_md(base / sid / "SKILL.md")
        skills[sid] = meta
    return skills


def resolve_skill_id(skill_id: str, root: Path | None = None) -> str:
    aliases = load_aliases(root)
    seen: set[str] = set()
    current = skill_id
    while current in aliases:
        if current in seen:
            break
        seen.add(current)
        current = aliases[current]
    return current


def find_skill(task: str, root: Path | None = None) -> tuple[str, float]:
    task_l = task.lower()
    skills = load_all_skills(root)
    aliases = load_aliases(root)
    best_id = COFOUNDER_SKILL if COFOUNDER_SKILL in skills else "bosskuai-workspace-assistant"
    best_score = 0.0
    for sid, meta in skills.items():
        score = _score(task_l, meta)
        if score > best_score:
            best_score = score
            best_id = sid
    for alias, target in aliases.items():
        if alias.replace("bosskuai-", "").replace("-", " ") in task_l:
            if target in skills:
                return target, 1.5
    return resolve_skill_id(best_id, root), best_score


def _score(task: str, meta: SkillMeta) -> float:
    score = 0.0
    if meta.skill_id.replace("bosskuai-", "").replace("-", " ") in task:
        score += 2.0
    if meta.skill_id in task:
        score += 3.0
    for trigger in meta.triggers:
        if trigger.lower() in task:
            score += 2.5
    for kw in meta.keywords:
        if kw.lower() in task:
            score += 1.0
    desc = meta.description.lower()
    if task in desc:
        score += 6.0
    words = [w.strip(".,!?") for w in task.split()]
    if len(words) >= 2:
        for i in range(len(words) - 1):
            bigram = f"{words[i]} {words[i + 1]}"
            if bigram in desc:
                score += 4.0
    for part in meta.skill_id.replace("bosskuai-", "").split("-"):
        if len(part) >= 3 and part in task:
            score += 1.5
    for word in words:
        if len(word) >= 5 and word in desc:
            score += 0.5
    return score


def write_routing_cache(dest: Path, root: Path | None = None) -> None:
    skills = load_all_skills(root)
    aliases = load_aliases(root)
    payload = {
        "version": "2.0.0",
        "skills": [
            {
                "id": sid,
                "name": meta.name,
                "description": meta.description,
                "triggers": meta.triggers,
                "keywords": meta.keywords,
            }
            for sid, meta in sorted(skills.items())
        ],
        "aliases": aliases,
        "default_skill_id": COFOUNDER_SKILL if COFOUNDER_SKILL in skills else "bosskuai-workspace-assistant",
    }
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(json.dumps(payload, indent=2), encoding="utf-8")


def copy_skills_to(dest_dir: Path, root: Path | None = None, profile: str = "full") -> list[str]:
    base = skills_dir(root)
    dest_dir.mkdir(parents=True, exist_ok=True)
    selected = _profile_skills(profile, root)
    installed: list[str] = []
    for sid in selected:
        src = base / sid
        if not src.is_dir():
            continue
        target = dest_dir / sid
        if target.exists():
            shutil.rmtree(target)
        shutil.copytree(src, target)
        installed.append(sid)
    return installed


def _profile_skills(profile: str, root: Path | None) -> list[str]:
    core = [
        COFOUNDER_SKILL,
        "bosskuai-workspace-assistant",
        "bosskuai-project-understanding",
        "bosskuai-search-first",
        "bosskuai-human-output",
        "bosskuai-continuous-learning",
        "bosskuai-context-limit-continuation",
        "bosskuai-permanent-memory-orchestration",
        "bosskuai-engineering-delivery",
        "bosskuai-rigorous-code-review",
        "bosskuai-documentation-lookup",
        "bosskuai-ponytail",
        "bosskuai-taste",
    ]
    if profile == "core":
        return [s for s in core if (skills_dir(root) / s).is_dir()]
    return list_skill_ids(root)


def is_managed_skill_name(name: str, root: Path | None = None) -> bool:
    if name == COFOUNDER_SKILL or name.startswith(MANAGED_SKILL_PREFIX):
        return True
    return name in load_vendored_ids(root)


def validate_skills(root: Path | None = None) -> list[str]:
    errors: list[str] = []
    base = skills_dir(root)
    aliases = load_aliases(root)
    ids = set(list_skill_ids(root))
    for alias, target in aliases.items():
        if alias in ids:
            errors.append(f"alias {alias} conflicts with real skill folder")
        if target not in ids and target not in aliases.values():
            errors.append(f"alias {alias} points to missing skill {target}")
    for sid in ids:
        skill_md = base / sid / "SKILL.md"
        if not skill_md.is_file():
            errors.append(f"missing SKILL.md for {sid}")
            continue
        text = skill_md.read_text(encoding="utf-8")
        if not text.startswith("---"):
            errors.append(f"{sid}/SKILL.md missing YAML frontmatter")
    seen: set[str] = set()
    for sid in ids:
        if sid in seen:
            errors.append(f"duplicate skill id {sid}")
        seen.add(sid)
    return errors
