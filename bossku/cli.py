from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from bossku import __version__
from bossku.doctor import format_doctor_success, gather_doctor_issues
from bossku.hooks import install_hooks, run_sync_hook, uninstall_hooks
from bossku.init_project import init_project
from bossku.install import install_user, uninstall_user, update_user
from bossku.memory import remember, sync_project
from bossku.index import write_index
from bossku.skills import find_skill, overdue_packs, pack_stocktake, rank_skills
from bossku.validate import validate_repo

CONFIDENT_SCORE = 6.0
CONFIDENT_MARGIN = 1.25


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
    p_doctor = sub.add_parser("doctor", help="Check install health", parents=[parent])
    p_doctor.add_argument(
        "--project",
        type=Path,
        default=None,
        help="Also verify project AGENTS.md + CLAUDE.md adapters",
    )

    p_remember = sub.add_parser("remember", help="Save curated memory", parents=[parent])
    p_remember.add_argument("--kind", required=True, choices=["decision", "plan", "learning", "project"])
    p_remember.add_argument("--project", type=Path, required=True)
    p_remember.add_argument("note")

    p_sync = sub.add_parser("sync", help="Export project memory to Obsidian", parents=[parent])
    p_sync.add_argument("--project", type=Path, required=True)

    p_sync_hook = sub.add_parser(
        "sync-hook",
        help="Internal: run from a tool session-end hook; reads project cwd from stdin JSON",
        parents=[parent],
    )
    p_sync_hook.add_argument("--project", type=Path, default=None)

    p_hooks = sub.add_parser(
        "hooks", help="Manage session-end sync hooks (Claude Code, Cursor, Codex, OpenCode)", parents=[parent]
    )
    p_hooks_sub = p_hooks.add_subparsers(dest="hooks_cmd", required=True)
    p_hooks_install = p_hooks_sub.add_parser("install", parents=[parent])
    p_hooks_install.add_argument(
        "--tools", type=str, default=None, help="comma-separated subset: claude_code,cursor,codex,opencode"
    )
    p_hooks_uninstall = p_hooks_sub.add_parser("uninstall", parents=[parent])
    p_hooks_uninstall.add_argument("--tools", type=str, default=None)

    p_find = sub.add_parser("skills", help="Skill utilities", parents=[parent])
    p_find_sub = p_find.add_subparsers(dest="skills_cmd", required=True)
    p_find_cmd = p_find_sub.add_parser("find", parents=[parent])
    p_find_cmd.add_argument("task")
    p_find_cmd.add_argument("--limit", type=int, default=5, help="shortlist size")
    p_find_sub.add_parser("index", help="Rebuild skills/skill-index.json", parents=[parent])
    p_stock = p_find_sub.add_parser(
        "stocktake", help="Age vendored packs against the review window", parents=[parent]
    )
    p_stock.add_argument("--strict", action="store_true", help="exit 1 when a pack is overdue")
    p_stock.add_argument("--json", action="store_true", dest="as_json")

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
            return _doctor(root, home, getattr(args, "project", None))
        if args.command == "remember":
            result = remember(args.project, args.kind, args.note, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "sync":
            result = sync_project(args.project, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "sync-hook":
            result = run_sync_hook(project=args.project, home=home)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "hooks":
            tools = tuple(args.tools.split(",")) if args.tools else None
            if args.hooks_cmd == "install":
                result = install_hooks(home=home, tools=tools)
            else:
                result = uninstall_hooks(home=home, tools=tools)
            print(json.dumps(result, indent=2))
            return 0
        if args.command == "skills":
            if args.skills_cmd == "find":
                sid, score = find_skill(args.task, root)
                matches = rank_skills(args.task, root, limit=max(args.limit, 1))
                runner_up = matches[1][1] if len(matches) > 1 else 0.0
                print(
                    json.dumps(
                        {
                            "skill_id": sid,
                            "score": score,
                            # Lexical matching is a fallback, not an oracle: say so when the
                            # top hit is weak or barely beats the next one, and read the
                            # shortlist instead of trusting skill_id.
                            "confident": score >= CONFIDENT_SCORE
                            and score >= runner_up * CONFIDENT_MARGIN,
                            "matches": [
                                {"skill_id": s, "score": round(v, 3)} for s, v in matches
                            ],
                        },
                        indent=2,
                    )
                )
            elif args.skills_cmd == "index":
                dest = write_index(root)
                print(json.dumps({"index": str(dest)}, indent=2))
            elif args.skills_cmd == "stocktake":
                return _stocktake(root, strict=args.strict, as_json=args.as_json)
            return 0
        if args.command == "validate":
            errors = validate_repo(root)
            if errors:
                for err in errors:
                    print(err, file=sys.stderr)
                return 1
            print("validate: ok")
            # A pack going stale is a prompt to review, not a broken repo: warn, stay green.
            stale = overdue_packs(root)
            if stale:
                print(
                    f"warning: {len(stale)} vendored pack(s) due for review "
                    f"({', '.join(stale)}); run `bossku skills stocktake`"
                )
            return 0
        if args.command == "uninstall":
            result = uninstall_user(root=root, home=home, purge=args.purge)
            print(json.dumps(result, indent=2))
            return 0
    except Exception as exc:  # noqa: BLE001 - CLI boundary
        print(f"error: {exc}", file=sys.stderr)
        return 2
    return 0


def _stocktake(root: Path | None, *, strict: bool = False, as_json: bool = False) -> int:
    rows = pack_stocktake(root)
    if as_json:
        print(json.dumps(rows, indent=2))
    else:
        print(f"{'PACK':<20}{'SKILLS':>7}{'LAST SYNCED':>14}{'AGE':>7}  STATUS")
        for r in rows:
            age = "never" if r["age_days"] is None else f"{r['age_days']}d"
            status = "OVERDUE" if r["overdue"] else "ok"
            print(f"{r['pack']:<20}{r['skills']:>7}{r['last_synced'] or '-':>14}{age:>7}  {status}")
        overdue = [r for r in rows if r["overdue"]]
        window = rows[0]["review_days"] if rows else 0
        print()
        if overdue:
            print(f"{len(overdue)} pack(s) past the {window}-day review window:")
            for r in overdue:
                print(f"  {r['pack']}: re-vendor from {r['upstream'] or 'upstream'}, "
                      f"then update last_synced in skills/vendored.json")
        else:
            print(f"all {len(rows)} packs within the {window}-day review window.")
        print("note: dates are recorded syncs, not a live upstream check.")
    return 1 if strict and any(r["overdue"] for r in rows) else 0


def _doctor(root: Path | None, home: Path | None, project: Path | None = None) -> int:
    issues = gather_doctor_issues(root, home, project=project)
    if issues:
        print("doctor: issues found")
        for item in issues:
            print(f"  - {item}")
        return 1
    for line in format_doctor_success(root, home, version=__version__):
        print(line)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
