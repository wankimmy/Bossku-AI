from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from bossku import __version__
from bossku.init_project import init_project
from bossku.install import install_user, uninstall_user, update_user
from bossku.memory import load_user_config, remember, save_user_config, sync_project
from bossku.paths import agents_skills_dir, claude_skills_dir, repo_root
from bossku.skills import find_skill, validate_skills
from bossku.validate import validate_repo


def main(argv: list[str] | None = None) -> int:
    parent = argparse.ArgumentParser(add_help=False)
    parent.add_argument("--root", type=Path, default=None, help="BosskuAI repo root")
    parent.add_argument("--home", type=Path, default=None, help="Override home for tests")

    parser = argparse.ArgumentParser(prog="bossku", description="BosskuAI toolkit CLI", parents=[parent])
    sub = parser.add_subparsers(dest="command", required=True)

    p_install = sub.add_parser("install", help="Install skills to user-level agent dirs", parents=[parent])
    p_install.add_argument("--profile", choices=["core", "full"], default="full")
    p_install.add_argument("--vault", type=str, default=None, help="Obsidian vault path")

    p_init = sub.add_parser("init", help="Initialize project adapter", parents=[parent])
    p_init.add_argument("project", type=Path)
    p_init.add_argument("--portable", action="store_true")
    p_init.add_argument("--profile", choices=["core", "full"], default="core")

    sub.add_parser("update", help="Refresh user-level skills from repo", parents=[parent])
    sub.add_parser("doctor", help="Check install health", parents=[parent])

    p_remember = sub.add_parser("remember", help="Save curated memory", parents=[parent])
    p_remember.add_argument("--kind", required=True, choices=["decision", "plan", "learning", "project"])
    p_remember.add_argument("--project", type=Path, required=True)
    p_remember.add_argument("note")

    p_sync = sub.add_parser("sync", help="Export project memory to Obsidian", parents=[parent])
    p_sync.add_argument("--project", type=Path, required=True)

    p_find = sub.add_parser("skills", help="Skill utilities", parents=[parent])
    p_find_sub = p_find.add_subparsers(dest="skills_cmd", required=True)
    p_find_cmd = p_find_sub.add_parser("find", parents=[parent])
    p_find_cmd.add_argument("task")

    sub.add_parser("validate", help="Validate repository layout", parents=[parent])
    p_uninstall = sub.add_parser("uninstall", help="Remove user-level BosskuAI skills", parents=[parent])
    p_uninstall.add_argument("--purge", action="store_true")

    args = parser.parse_args(argv)
    root = args.root
    home = args.home

    try:
        if args.command == "install":
            result = install_user(root=root, home=home, profile=args.profile, vault=args.vault)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "init":
            result = init_project(args.project, root=root, portable=args.portable, profile=args.profile)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "update":
            result = update_user(root=root, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "doctor":
            return _doctor(root, home)
        if args.command == "remember":
            result = remember(args.project, args.kind, args.note, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "sync":
            result = sync_project(args.project, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "skills":
            if args.skills_cmd == "find":
                sid, score = find_skill(args.task, root)
                print(json.dumps({"skill_id": sid, "score": score}, indent=2))
            return 0
        if args.command == "validate":
            errors = validate_repo(root)
            if errors:
                for err in errors:
                    print(err, file=sys.stderr)
                return 1
            print("validate: ok")
            return 0
        if args.command == "uninstall":
            result = uninstall_user(root=root, home=home, purge=args.purge)
            print(json.dumps(result, indent=2))
            return 0
    except Exception as exc:  # noqa: BLE001 - CLI boundary
        print(f"error: {exc}", file=sys.stderr)
        return 2
    return 0


def _doctor(root: Path | None, home: Path | None) -> int:
    issues: list[str] = []
    try:
        repo_root(root)
    except FileNotFoundError as exc:
        issues.append(str(exc))
    issues.extend(validate_skills(root))
    cfg = load_user_config(home)
    if not cfg.get("installed_from"):
        issues.append("user install not detected; run `bossku install`")
    else:
        h = home if home is not None else Path.home()
        for label, path in (
            ("~/.agents/skills", agents_skills_dir(h)),
            ("~/.claude/skills", claude_skills_dir(h)),
        ):
            if not path.is_dir():
                issues.append(f"missing skill directory {label}; run `bossku install`")
    if issues:
        print("doctor: issues found")
        for item in issues:
            print(f"  - {item}")
        return 1
    print(f"doctor: ok (bossku {__version__})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
