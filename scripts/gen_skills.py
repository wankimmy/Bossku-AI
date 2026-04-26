#!/usr/bin/env python3
"""
BosskuAI gen_skills.py — generate / validate boilerplate skill SKILL.md files.

Usage:
  python3 scripts/gen_skills.py [--root .] [--check] [--fix]

Modes:
  --check  Report which skill files have drifted from the template (default).
  --fix    Regenerate boilerplate skill files from skill-index.json + template.
           Skills with unique sections beyond the 4 standard headings are skipped.

Skills treated as hand-authored (never auto-generated):
  - Any skill whose SKILL.md contains unique section headings beyond the 4 boilerplate ones.
  - Skills listed in HAND_AUTHORED_SKILLS below.
"""
from __future__ import annotations
import argparse, json, re, sys
from pathlib import Path

BOILERPLATE_HEADINGS = {
    "fast path",
    "when to open the playbook",
    "default output",
    "verification",
    "load this skill when",
    "operating stance",
    "core workflow",
    "guardrails",
    "output format",
    "references",
}

# Skills with meaningful unique content — never auto-generated
HAND_AUTHORED_SKILLS = {
    "cofounder",
    "bosskuai-workspace-assistant",
    "bosskuai-continuous-learning",
    "bosskuai-context-limit-continuation",
    "bosskuai-documentation-lookup",
    "bosskuai-token-saver",
    "bosskuai-human-output",
    "bosskuai-coding-best-practices",
    "bosskuai-product-strategy",
    "bosskuai-project-understanding",
    "bosskuai-rigorous-code-review",
    "bosskuai-docker",
    "bosskuai-3d-web-development",
    "bosskuai-lenis-smooth-scroll",
}

SKILL_TEMPLATE = """\
---
name: {name}
description: {description}
---

# {title}

{description}

## Fast Path

1. Confirm the requested outcome and constraints.
2. Use the smallest checklist needed; do not load the full playbook by default.
3. Produce the artifact, review, or decision in the user-requested format.
4. State verification performed and any remaining risk.

## When To Open The Playbook

Open `../../references/playbooks/{playbook_name}` only when the task needs detailed framework choices, longer checklists, examples, or implementation depth.

## Default Output

- Start with the answer or changed recommendation.
- Use concise bullets for tradeoffs.
- Avoid generic AI/SaaS phrasing.
- For implementation work, include exact files, commands, tests, or review notes.

## Verification

Before finalizing, check:

- Did the output solve the actual request?
- Are assumptions and risks visible?
- Is there a concrete next action?
- Did we avoid loading unnecessary specialist context?
"""


def skill_title(skill_id: str) -> str:
    """Convert skill-id to display title."""
    name = skill_id.removeprefix("bosskuai-")
    return " ".join(w.capitalize() for w in name.replace("-", " ").split())


def playbook_name(skill_id: str) -> str:
    return f"{skill_id}-playbook.md"


def unique_headings(skill_md: str) -> list[str]:
    """Return headings in a SKILL.md that are NOT boilerplate."""
    headings = re.findall(r"^#{1,6}\s+(.+)$", skill_md, re.MULTILINE)
    unique = []
    for h in headings:
        normalized = h.strip().lower()
        if normalized not in BOILERPLATE_HEADINGS:
            unique.append(h)
    return unique


def is_hand_authored(skill_id: str, skill_md: str) -> bool:
    if skill_id in HAND_AUTHORED_SKILLS:
        return True
    return bool(unique_headings(skill_md))


def get_description(skill_id: str, idx_skills: list[dict], skill_md: str) -> str:
    """Extract description from skill-index or SKILL.md frontmatter."""
    # Try skill-index description field first
    for s in idx_skills:
        if s["id"] == skill_id and s.get("description"):
            return s["description"]
    # Fall back to SKILL.md frontmatter
    m = re.search(r"^description:\s*(.+)$", skill_md, re.MULTILINE)
    if m:
        return m.group(1).strip()
    return f"Use this for {skill_title(skill_id).lower()} tasks."


def main() -> int:
    ap = argparse.ArgumentParser(description="Generate / validate BosskuAI skill files.")
    ap.add_argument("--root", default=".")
    ap.add_argument("--check", action="store_true", help="Report drift without making changes (default).")
    ap.add_argument("--fix", action="store_true", help="Regenerate boilerplate skills from template.")
    args = ap.parse_args()

    if not args.fix:
        args.check = True

    root = Path(args.root).resolve()
    idx = json.loads((root / "skill-index.json").read_text(encoding="utf-8"))
    idx_skills = idx.get("skills", [])
    skills_dir = root / "ai-assistant/skills"

    drifted: list[str] = []
    hand_authored: list[str] = []
    generated: list[str] = []
    skipped_deprecated: list[str] = []

    for skill_dir in sorted(skills_dir.iterdir()):
        if not skill_dir.is_dir() or skill_dir.name.startswith("_"):
            continue
        skill_id = skill_dir.name
        skill_file = skill_dir / "SKILL.md"
        if not skill_file.exists():
            continue

        # Find skill in index
        idx_entry = next((s for s in idx_skills if s["id"] == skill_id), None)
        if idx_entry and idx_entry.get("status") == "deprecated_alias":
            skipped_deprecated.append(skill_id)
            continue

        current = skill_file.read_text(encoding="utf-8")

        if is_hand_authored(skill_id, current):
            hand_authored.append(skill_id)
            continue

        description = get_description(skill_id, idx_skills, current)
        title = f"BosskuAI {skill_title(skill_id)}"
        expected = SKILL_TEMPLATE.format(
            name=skill_id,
            description=description,
            title=title,
            playbook_name=playbook_name(skill_id),
        )

        if current.strip() != expected.strip():
            drifted.append(skill_id)
            if args.fix:
                skill_file.write_text(expected, encoding="utf-8")
                generated.append(skill_id)
        else:
            pass  # already matches template

    print("BosskuAI skill file audit")
    print(f"  Hand-authored (preserved): {len(hand_authored)}")
    print(f"  Deprecated aliases (skipped): {len(skipped_deprecated)}")
    print(f"  Boilerplate drifted from template: {len(drifted)}")
    if drifted:
        for s in drifted:
            status = "  FIXED" if s in generated else "  DRIFT"
            print(f"    {status}: {s}")
    if args.fix and generated:
        print(f"  Regenerated: {len(generated)} files")
    elif drifted and not args.fix:
        print("  Run with --fix to regenerate drifted files.")
    print(f"\nStatus: {'PASS' if not drifted or args.fix else 'FAIL — run with --fix'}")
    return 0 if not drifted or args.fix else 1


if __name__ == "__main__":
    raise SystemExit(main())
