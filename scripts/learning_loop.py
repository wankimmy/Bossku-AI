#!/usr/bin/env python3
"""
BosskuAI learning_loop.py — close the continuous-learning loop.

What it does:
  1. Reads learning-log.md for applied, high-confidence entries.
  2. Extracts the skill/domain each lesson is about.
  3. Proposes trigger and keyword additions to skill-index.json.
  4. Writes an ADR with the proposed changes.
  5. Optionally applies them to skill-index.json.

This turns the learning journal into a mechanism that actually improves routing,
not just a record of what was learned.

Usage:
  python3 scripts/learning_loop.py [--root .] [--check] [--apply]
  python3 scripts/learning_loop.py --root . --apply  # apply proposals to skill-index.json

After running with --apply, verify routing still passes:
  python3 -S scripts/eval_workspace.py
"""
from __future__ import annotations
import argparse, datetime, json, re, sys
from pathlib import Path

ENTRY_RE = re.compile(r"^(### .+?)\n((?:(?!^###).|\n)+)", re.MULTILINE)
STATUS_RE = re.compile(r"\*\*Status:\*\*\s*(\S+)", re.IGNORECASE)
CONFIDENCE_RE = re.compile(r"\*\*Confidence:\*\*\s*(\S+)", re.IGNORECASE)
SIGNAL_RE = re.compile(r"\*\*Signal:\*\*\s*(.+?)(?=\n\*\*|\Z)", re.IGNORECASE | re.DOTALL)
DECISION_RE = re.compile(r"\*\*Decision[^:]*:\*\*\s*(.+?)(?=\n\*\*|\Z)", re.IGNORECASE | re.DOTALL)
SKILL_RE = re.compile(r"`(bosskuai-[a-z-]+|cofounder)`")

TOKEN_RE = re.compile(r"[a-z][a-z0-9]{2,}")
STOP_WORDS = {
    "the", "and", "for", "this", "that", "with", "from", "into", "when", "not",
    "are", "was", "has", "have", "will", "can", "should", "would", "been", "use",
    "used", "using", "add", "new", "also", "then", "than", "only", "each",
}


def extract_keywords(text: str, top_n: int = 5) -> list[str]:
    tokens = TOKEN_RE.findall(text.lower())
    freq: dict[str, int] = {}
    for t in tokens:
        if t not in STOP_WORDS:
            freq[t] = freq.get(t, 0) + 1
    return [w for w, _ in sorted(freq.items(), key=lambda x: -x[1])[:top_n]]


def parse_log(log_path: Path) -> list[dict]:
    content = log_path.read_text(encoding="utf-8")
    entries = []
    for m in ENTRY_RE.finditer(content):
        body = m.group(2)
        status = (STATUS_RE.search(body) or type("", (), {"group": lambda self, n: "unknown"})()).group(1).lower() if STATUS_RE.search(body) else "unknown"
        confidence = CONFIDENCE_RE.search(body)
        signal = SIGNAL_RE.search(body)
        decision = DECISION_RE.search(body)
        skills_mentioned = SKILL_RE.findall(body)
        entries.append({
            "title": m.group(1).strip(),
            "status": status,
            "confidence": confidence.group(1).lower() if confidence else "unknown",
            "signal": signal.group(1).strip() if signal else "",
            "decision": decision.group(1).strip() if decision else "",
            "skills": skills_mentioned,
        })
    return entries


def build_proposals(entries: list[dict], idx: dict) -> list[dict]:
    """Generate routing enhancement proposals from applied, high-confidence entries."""
    existing_skills = {s["id"]: s for s in idx.get("skills", [])}
    proposals: list[dict] = []

    for entry in entries:
        if entry["status"] != "applied":
            continue
        if entry["confidence"] not in ("high", "medium"):
            continue
        if not entry["skills"]:
            continue

        combined_text = f"{entry['signal']} {entry['decision']}"
        new_keywords = extract_keywords(combined_text, top_n=4)

        for skill_id in entry["skills"]:
            if skill_id not in existing_skills:
                continue
            skill = existing_skills[skill_id]
            existing_kw = set(skill.get("keywords", []))
            existing_tr = set(skill.get("triggers", []))
            new_kw = [k for k in new_keywords if k not in existing_kw and len(k) > 3]
            if not new_kw:
                continue
            proposals.append({
                "skill_id": skill_id,
                "source_entry": entry["title"],
                "proposed_keywords": new_kw,
                "rationale": entry["decision"][:200].strip(),
            })

    return proposals


def write_adr(proposals: list[dict], root: Path) -> Path:
    today = datetime.date.today().isoformat()
    adr_dir = root / "ai-assistant/references/adr"
    adr_dir.mkdir(parents=True, exist_ok=True)
    adr_path = adr_dir / f"{today}-learning-loop-routing-update.md"

    lines = [
        f"# ADR: Learning Loop Routing Update — {today}\n",
        "**Source:** learning_loop.py automated proposal\n",
        f"**Date:** {today}\n",
        "**Status:** proposed\n\n",
        "## Proposals\n",
    ]
    for p in proposals:
        lines.append(f"\n### {p['skill_id']}\n")
        lines.append(f"- **Source entry:** {p['source_entry']}\n")
        lines.append(f"- **Proposed keywords:** {', '.join(p['proposed_keywords'])}\n")
        lines.append(f"- **Rationale:** {p['rationale']}\n")

    lines.append("\n## Verification\n")
    lines.append("After applying, run: `python3 -S scripts/eval_workspace.py`\n")
    lines.append("Routing score must remain 13/13 or improve.\n")

    adr_path.write_text("".join(lines), encoding="utf-8")
    return adr_path


def apply_proposals(proposals: list[dict], idx_path: Path, idx: dict) -> int:
    applied = 0
    for p in proposals:
        for skill in idx["skills"]:
            if skill["id"] == p["skill_id"]:
                existing = set(skill.get("keywords", []))
                added = [k for k in p["proposed_keywords"] if k not in existing]
                skill.setdefault("keywords", []).extend(added)
                applied += len(added)
                break
    idx_path.write_text(json.dumps(idx, indent=2, ensure_ascii=False), encoding="utf-8")
    return applied


def main() -> int:
    ap = argparse.ArgumentParser(description="Close the continuous-learning loop into routing improvements.")
    ap.add_argument("--root", default=".")
    ap.add_argument("--check", action="store_true", help="Show proposals without applying (default).")
    ap.add_argument("--apply", action="store_true", help="Apply proposals to skill-index.json and write ADR.")
    args = ap.parse_args()

    if not args.apply:
        args.check = True

    root = Path(args.root).resolve()
    log_path = root / "ai-assistant/memory/learning-log.md"
    idx_path = root / "skill-index.json"

    if not log_path.exists():
        print(f"learning-log.md not found: {log_path}", file=sys.stderr)
        return 1
    if not idx_path.exists():
        print(f"skill-index.json not found: {idx_path}", file=sys.stderr)
        return 1

    entries = parse_log(log_path)
    idx = json.loads(idx_path.read_text(encoding="utf-8"))
    proposals = build_proposals(entries, idx)

    print("BosskuAI learning loop")
    print(f"  Entries processed: {len(entries)}")
    print(f"  Applied+high/medium confidence: {sum(1 for e in entries if e['status'] == 'applied' and e['confidence'] in ('high','medium'))}")
    print(f"  Routing proposals: {len(proposals)}")

    if not proposals:
        print("\nNo new routing proposals. Either no applied entries with skill mentions, or all keywords already present.")
        return 0

    print("\nProposals:")
    for p in proposals:
        print(f"  {p['skill_id']}: add keywords {p['proposed_keywords']}")
        print(f"    source: {p['source_entry']}")

    if args.apply:
        adr_path = write_adr(proposals, root)
        applied_count = apply_proposals(proposals, idx_path, idx)
        print(f"\nApplied {applied_count} new keyword(s) to skill-index.json.")
        print(f"ADR written: {adr_path.relative_to(root)}")
        print("\nNext: verify routing still passes:")
        print("  python3 -S scripts/eval_workspace.py")
    else:
        print("\nRun with --apply to update skill-index.json and write an ADR.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
