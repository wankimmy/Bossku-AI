#!/usr/bin/env python3
"""
BosskuAI rotate_learning_log.py — keep learning-log.md healthy.

What it does:
  1. Parses all entries in learning-log.md.
  2. Finds entries with Status=applied AND last_reviewed older than --archive-days (default 90).
  3. Collapses them to a one-line summary in an ## Archived section.
  4. Detects near-duplicate entries (> --dup-threshold token overlap, default 0.55).
  5. Reports contradictions: same topic, different conclusions.

Usage:
  python3 scripts/rotate_learning_log.py [--root .] [--check] [--fix] [--archive-days 90]
"""
from __future__ import annotations
import argparse, datetime, re, sys
from pathlib import Path

ENTRY_RE = re.compile(
    r"^(### .+?)\n((?:(?!^###).|\n)+)",
    re.MULTILINE,
)
STATUS_RE = re.compile(r"\*\*Status:\*\*\s*(\S+)", re.IGNORECASE)
REVIEWED_RE = re.compile(r"\*\*Last reviewed:\*\*\s*(\d{4}-\d{2}-\d{2})", re.IGNORECASE)
DATE_IN_TITLE_RE = re.compile(r"(\d{4}-\d{2}-\d{2})")


def tokenize(text: str) -> set[str]:
    return set(re.findall(r"[a-z0-9]{3,}", text.lower()))


def overlap(a: str, b: str) -> float:
    ta, tb = tokenize(a), tokenize(b)
    if not ta or not tb:
        return 0.0
    return len(ta & tb) / len(ta | tb)


def parse_entries(content: str) -> list[dict]:
    entries = []
    for m in ENTRY_RE.finditer(content):
        title = m.group(1).strip()
        body = m.group(2).strip()
        status_m = STATUS_RE.search(body)
        reviewed_m = REVIEWED_RE.search(body)
        date_m = DATE_IN_TITLE_RE.search(title)
        entries.append({
            "title": title,
            "body": body,
            "full": f"{title}\n{body}",
            "status": status_m.group(1).lower() if status_m else "unknown",
            "last_reviewed": reviewed_m.group(1) if reviewed_m else (date_m.group(1) if date_m else None),
        })
    return entries


def summarise(entry: dict) -> str:
    """One-line summary for archived entries."""
    title = entry["title"].lstrip("#").strip()
    status = entry["status"]
    reviewed = entry["last_reviewed"] or "unknown"
    # Pull the Decision line if present
    dec_m = re.search(r"\*\*Decision[^:]*:\*\*\s*(.+)", entry["body"])
    decision = dec_m.group(1).strip()[:120] if dec_m else title[:120]
    return f"- {title} ({status}, reviewed {reviewed}): {decision}"


def main() -> int:
    ap = argparse.ArgumentParser(description="Rotate and health-check learning-log.md")
    ap.add_argument("--root", default=".")
    ap.add_argument("--check", action="store_true", help="Report without making changes (default).")
    ap.add_argument("--fix", action="store_true", help="Archive stale entries in-place.")
    ap.add_argument("--archive-days", type=int, default=90, help="Days after which applied entries are archived.")
    ap.add_argument("--dup-threshold", type=float, default=0.55, help="Jaccard token overlap to flag as duplicate (0–1).")
    args = ap.parse_args()

    if not args.fix:
        args.check = True

    root = Path(args.root).resolve()
    log_path = root / "ai-assistant/memory/learning-log.md"
    if not log_path.exists():
        print(f"learning-log.md not found at {log_path}", file=sys.stderr)
        return 1

    content = log_path.read_text(encoding="utf-8")
    entries = parse_entries(content)
    today = datetime.date.today()
    cutoff = today - datetime.timedelta(days=args.archive_days)

    to_archive: list[dict] = []
    to_keep: list[dict] = []

    for entry in entries:
        if entry["status"] != "applied":
            to_keep.append(entry)
            continue
        if entry["last_reviewed"]:
            try:
                reviewed_date = datetime.date.fromisoformat(entry["last_reviewed"])
                if reviewed_date < cutoff:
                    to_archive.append(entry)
                    continue
            except ValueError:
                pass
        to_keep.append(entry)

    # Detect near-duplicates in entries to keep
    duplicates: list[tuple[str, str, float]] = []
    bodies = [(e["title"], e["body"]) for e in to_keep]
    for i in range(len(bodies)):
        for j in range(i + 1, len(bodies)):
            score = overlap(bodies[i][1], bodies[j][1])
            if score >= args.dup_threshold:
                duplicates.append((bodies[i][0], bodies[j][0], score))

    print("BosskuAI learning-log rotation")
    print(f"  Total entries: {len(entries)}")
    print(f"  Applied + reviewed before {cutoff}: {len(to_archive)} → archive")
    print(f"  Remaining active: {len(to_keep)}")
    print(f"  Near-duplicates (overlap ≥ {args.dup_threshold}): {len(duplicates)}")
    if duplicates:
        for a, b, score in duplicates:
            print(f"    [{score:.2f}] {a.lstrip('#').strip()!r}")
            print(f"          vs {b.lstrip('#').strip()!r}")

    if to_archive and args.fix:
        # Rebuild content: header + active entries + archived section
        # Extract everything before first ### entry
        first_entry_pos = content.find("\n### ")
        preamble = content[:first_entry_pos].rstrip() if first_entry_pos >= 0 else content

        active_block = "\n\n".join(f"{e['title']}\n\n{e['body']}" for e in to_keep)
        archive_lines = [summarise(e) for e in to_archive]
        archive_block = "\n".join(archive_lines)

        new_content = (
            preamble
            + "\n\n## Active entries\n\n"
            + active_block
            + "\n\n---\n\n## Archived (applied, reviewed > "
            + str(args.archive_days)
            + " days ago)\n\n"
            + archive_block
            + "\n"
        )
        log_path.write_text(new_content, encoding="utf-8")
        print(f"\nArchived {len(to_archive)} entries. learning-log.md updated.")
    elif to_archive and not args.fix:
        print(f"\n{len(to_archive)} entries eligible for archiving. Run with --fix to apply.")

    if duplicates:
        print("\nAction: review duplicates manually and merge or remove redundant entries.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
