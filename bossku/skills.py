from __future__ import annotations

import json
import math
import re
import shutil
from dataclasses import dataclass
from datetime import date
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


DEFAULT_REVIEW_DAYS = 180


def load_provenance(root: Path | None = None) -> tuple[dict[str, dict], int]:
    path = vendored_path(root)
    if not path.is_file():
        return {}, DEFAULT_REVIEW_DAYS
    data = json.loads(path.read_text(encoding="utf-8"))
    review_days = int(data.get("review_days", DEFAULT_REVIEW_DAYS))
    return data.get("provenance", {}), review_days


def pack_stocktake(root: Path | None = None, today: date | None = None) -> list[dict]:
    """Age each vendored pack against the review window.

    `last_synced` is a recorded date, not an upstream check: this reports that a pack
    is due for review, never that upstream actually changed.
    """
    path = vendored_path(root)
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    provenance, review_days = load_provenance(root)
    now = today or date.today()

    rows: list[dict] = []
    for pack, ids in data.get("packs", {}).items():
        meta = provenance.get(pack, {})
        synced_raw = meta.get("last_synced")
        try:
            synced = date.fromisoformat(synced_raw) if synced_raw else None
        except ValueError:
            synced = None
        age = (now - synced).days if synced else None
        rows.append(
            {
                "pack": pack,
                "skills": len(ids),
                "upstream": meta.get("upstream", ""),
                "last_synced": synced_raw or "",
                "age_days": age,
                "review_days": review_days,
                # An unrecorded sync date is treated as due: silence should not read as fresh.
                "overdue": age is None or age > review_days,
            }
        )
    rows.sort(key=lambda r: (-1 if r["age_days"] is None else -r["age_days"]))
    return rows


def overdue_packs(root: Path | None = None, today: date | None = None) -> list[str]:
    return [r["pack"] for r in pack_stocktake(root, today) if r["overdue"]]


def load_pack_skill_ids(pack_name: str, root: Path | None = None) -> list[str]:
    path = vendored_path(root)
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    packs = data.get("packs", {})
    raw = packs.get(pack_name, [])
    return [str(sid) for sid in raw]


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
    # Close on a line that is exactly `---`; a bare find() would stop at any `---`
    # inside a description and silently truncate the frontmatter.
    match = re.search(r"^---[ \t]*$", text[3:], re.MULTILINE)
    if match is None:
        return {}
    block = text[3 : 3 + match.start()].strip()
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
            # Block scalars carry optional chomping/indent indicators (`>-`, `|+`, `>2`).
            # Matching only bare `>`/`|` turned `description: >-` into the string ">-".
            if re.fullmatch(r"[>|][-+]?\d?", val):
                folded = val.startswith(">")
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


def rank_skills(task: str, root: Path | None = None, limit: int = 5) -> list[tuple[str, float]]:
    """Rank skills for a task, best first. Uses skills/skill-index.json when present."""
    from bossku.index import build_index, compute_idf, load_index, tokenize, variants

    data = load_index(root)
    if data is None:
        data = build_index(root)
    entries: dict[str, dict] = data.get("skills", {})
    if not entries:
        return []

    idf = compute_idf(entries)
    task_l = " " + re.sub(r"\s+", " ", task.lower().strip()) + " "

    # Weight each query term by rarity, and remember its morphological variants once.
    default_idf = max(idf.values(), default=1.0)
    q_terms: dict[str, tuple[float, set[str]]] = {}
    for token in tokenize(task):
        if token not in q_terms:
            q_terms[token] = (idf.get(token, default_idf), variants(token))
    q_mass = sum(w for w, _ in q_terms.values()) or 1.0

    scored: list[tuple[str, float]] = []
    for sid, entry in entries.items():
        scored.append((sid, _score_entry(sid, entry, task_l, q_terms, q_mass)))
    scored.sort(key=lambda pair: (-pair[1], pair[0]))
    return scored[:limit]


def find_skill(task: str, root: Path | None = None) -> tuple[str, float]:
    from bossku.index import load_index

    aliases = load_aliases(root)
    task_l = task.lower()
    data = load_index(root)
    known = set((data or {}).get("skills", {})) or set(list_skill_ids(root))

    for alias, target in aliases.items():
        if alias.replace("bosskuai-", "").replace("-", " ") in task_l and target in known:
            return target, 1.5

    ranked = rank_skills(task, root, limit=1)
    fallback = COFOUNDER_SKILL if COFOUNDER_SKILL in known else "bosskuai-workspace-assistant"
    if not ranked or ranked[0][1] <= 0:
        return fallback, 0.0
    return resolve_skill_id(ranked[0][0], root), round(ranked[0][1], 3)


def _contains(haystack: str, phrase: str) -> bool:
    return f" {phrase} " in haystack


def _score_entry(
    sid: str,
    entry: dict,
    task_l: str,
    q_terms: dict[str, tuple[float, set[str]]],
    q_mass: float,
) -> float:
    """Score by how much of the query a skill explains, plus exact phrase evidence.

    Coverage-based rather than additive: an incidental word ("design *tokens*" hitting
    `token-saver`) explains one term out of several, so it cannot outrank a skill that
    accounts for the whole request.
    """
    from bossku.index import singular, tokenize

    ident = sid.replace("bosskuai-", "").replace("-", " ")
    id_tokens = {singular(t) for t in sid.replace("bosskuai-", "").split("-") if len(t) >= 2}
    triggers = entry.get("triggers", [])
    trigger_words = {w for t in triggers for w in tokenize(t)}
    keywords = set(entry.get("keywords", []))

    # How much of the query's information mass does this skill account for?
    matched = 0.0
    for weight, forms in q_terms.values():
        if forms & id_tokens:
            matched += weight * 3.0
        elif forms & trigger_words:
            matched += weight * 2.0
        elif forms & keywords:
            matched += weight * 1.0
    coverage = matched / (q_mass * 3.0)
    score = 18.0 * coverage

    # Exact multi-word phrases are precise evidence and survive on their own merit.
    for field, weight in (("triggers", 5.0), ("phrases", 1.8)):
        for phrase in entry.get(field, []):
            words = phrase.split()
            if len(words) >= 2 and _contains(task_l, phrase):
                score += weight + 0.9 * len(words)

    if _contains(task_l, sid) or _contains(task_l, ident):
        score += 3.0 if len(id_tokens) > 1 else 1.5

    return score


def write_routing_cache(dest: Path, root: Path | None = None) -> None:
    """Mirror the routing index next to the install so hosts get triggers, not just names."""
    from bossku.index import build_index, load_index

    data = load_index(root) or build_index(root)
    entries: dict[str, dict] = data.get("skills", {})
    payload = {
        "version": data.get("version", "2.1.0"),
        "fingerprint": data.get("fingerprint", ""),
        "skills": [
            {
                "id": sid,
                "name": entry.get("name", sid),
                "description": entry.get("description", ""),
                "triggers": entry.get("triggers", []),
                "keywords": entry.get("keywords", []),
                "model_role": entry.get("model_role", "coder"),
                "pack": entry.get("pack", "bossku"),
            }
            for sid, entry in sorted(entries.items())
        ],
        "aliases": load_aliases(root),
        "default_skill_id": COFOUNDER_SKILL if COFOUNDER_SKILL in entries else "bosskuai-workspace-assistant",
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
        base_dir = skills_dir(root)
        loop_ids = load_pack_skill_ids("loop-engineering", root)
        combined: list[str] = []
        seen: set[str] = set()
        for sid in [*core, *loop_ids]:
            if sid in seen:
                continue
            seen.add(sid)
            combined.append(sid)
        return [s for s in combined if (base_dir / s).is_dir()]
    return list_skill_ids(root)


def is_managed_skill_name(name: str, root: Path | None = None) -> bool:
    if name == COFOUNDER_SKILL or name.startswith(MANAGED_SKILL_PREFIX):
        return True
    return name in load_vendored_ids(root)


def count_managed_skills(dest_dir: Path, root: Path | None = None) -> int:
    if not dest_dir.is_dir():
        return 0
    total = 0
    for child in dest_dir.iterdir():
        if not child.is_dir():
            continue
        if not (child / "SKILL.md").is_file():
            continue
        if is_managed_skill_name(child.name, root):
            total += 1
    return total


KNOWN_FRONTMATTER_KEYS = frozenset(
    {
        "name",
        "description",
        "metadata",
        "license",
        "user_invocable",
        "allowed-tools",
        "argument-hint",
        "compatibility",
        "version",
        "triggers",
        "keywords",
        "model_role",
        "disable-model-invocation",
    }
)

# Hosts read `description` on every session, so it is a shared context budget.
MAX_DESCRIPTION_CHARS = 1200
MIN_DESCRIPTION_CHARS = 40


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
    for sid in sorted(ids):
        skill_md = base / sid / "SKILL.md"
        if not skill_md.is_file():
            errors.append(f"missing SKILL.md for {sid}")
            continue
        text = skill_md.read_text(encoding="utf-8")
        if not text.startswith("---"):
            errors.append(f"{sid}/SKILL.md missing YAML frontmatter")
            continue
        front = _parse_frontmatter(text)
        if not front:
            errors.append(f"{sid}/SKILL.md frontmatter did not parse")
            continue
        if not str(front.get("name", "")).strip():
            errors.append(f"{sid}/SKILL.md missing name")
        description = str(front.get("description", "")).strip()
        if not description:
            errors.append(f"{sid}/SKILL.md missing description")
        elif len(description) < MIN_DESCRIPTION_CHARS:
            errors.append(
                f"{sid}/SKILL.md description too short ({len(description)} chars); "
                "routing needs enough signal to match on"
            )
        elif len(description) > MAX_DESCRIPTION_CHARS:
            errors.append(
                f"{sid}/SKILL.md description too long ({len(description)} chars, "
                f"max {MAX_DESCRIPTION_CHARS}); it loads into every session"
            )
        unknown = sorted(set(front) - KNOWN_FRONTMATTER_KEYS)
        if unknown:
            errors.append(f"{sid}/SKILL.md unknown frontmatter key(s): {', '.join(unknown)}")
    return errors
