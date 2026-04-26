#!/usr/bin/env bash
# team_memory.sh — shared memory coordination for teams using the same repo.
#
# Commands:
#   tag-author    Tag upcoming learning-log entry with author name.
#   merge-logs    Merge two learning-log.md files chronologically.
#   check-conflicts  Detect conflicting lessons on the same topic.
#   install-hook  Install pre-commit hook to enforce entry format.
#
# Usage:
#   bash scripts/team_memory.sh <command> [options]

set -euo pipefail

cmd="${1:-help}"
shift || true
root="${1:-.}"

case "$cmd" in

tag-author)
  # Prepend author tag to the next entry written to learning-log.md.
  author="${2:-$(git config user.name 2>/dev/null || echo 'unknown')}"
  echo "# Author tag: $author ($(date '+%Y-%m-%d'))"
  echo "# Paste this at the top of your next learning-log.md entry:"
  echo "- **Author:** $author"
  ;;

merge-logs)
  # Merge two learning-log.md files into chronological order.
  file_a="${2:?Usage: team_memory.sh merge-logs <file-a> <file-b> [output]}"
  file_b="${3:?Missing file-b}"
  output="${4:--}"  # default: stdout

  python3 - "$file_a" "$file_b" "$output" << 'PYEOF2'
import re, sys
from pathlib import Path

file_a, file_b, output = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
ENTRY_RE = re.compile(r"^(### .+?)\n((?:(?!^###).|\n)+)", re.MULTILINE)
DATE_RE = re.compile(r"(\d{4}-\d{2}-\d{2})")

def parse_entries(path):
    content = path.read_text(encoding="utf-8")
    header = content[:content.find("\n### ")] if "\n### " in content else ""
    entries = []
    for m in ENTRY_RE.finditer(content):
        title = m.group(1).strip()
        body = m.group(2).strip()
        date_m = DATE_RE.search(title)
        date = date_m.group(1) if date_m else "0000-00-00"
        entries.append((date, title, body))
    return header, entries

header_a, entries_a = parse_entries(file_a)
_, entries_b = parse_entries(file_b)

seen = set()
merged = []
for date, title, body in entries_a + entries_b:
    key = title.lower().strip()
    if key not in seen:
        seen.add(key)
        merged.append((date, title, body))

merged.sort(key=lambda x: x[0])
result = header_a.strip() + "\n\n## Active entries\n\n"
result += "\n\n".join(f"{title}\n\n{body}" for _, title, body in merged)
result += "\n"

if output == "-":
    print(result)
else:
    Path(output).write_text(result, encoding="utf-8")
    print(f"Merged {len(merged)} entries → {output}")
PYEOF2
  ;;

check-conflicts)
  # Detect learning entries that appear to contradict each other.
  log="${2:-$root/ai-assistant/memory/learning-log.md}"
  python3 - "$log" << 'PYEOF2'
import re, sys
from pathlib import Path

log = Path(sys.argv[1])
content = log.read_text(encoding="utf-8")
ENTRY_RE = re.compile(r"^(### .+?)\n((?:(?!^###).|\n)+)", re.MULTILINE)
DECISION_RE = re.compile(r"\*\*Decision[^:]*:\*\*\s*(.+?)(?=\n\*\*|\Z)", re.IGNORECASE | re.DOTALL)
TOKEN_RE = re.compile(r"[a-z][a-z0-9]{3,}")
STOP = {"this","that","with","from","into","when","should","would","have","been","will","are","the","and","for","not","use"}

def topics(text):
    return set(TOKEN_RE.findall(text.lower())) - STOP

entries = []
for m in ENTRY_RE.finditer(content):
    title = m.group(1).strip()
    body = m.group(2).strip()
    dec_m = DECISION_RE.search(body)
    decision = dec_m.group(1).strip() if dec_m else ""
    entries.append({"title": title, "decision": decision, "topic_tokens": topics(title + " " + decision[:200])})

negation_words = {"not", "never", "avoid", "dont", "do not", "instead", "rather", "stop", "prevent"}
conflicts = []
for i in range(len(entries)):
    for j in range(i+1, len(entries)):
        a, b = entries[i], entries[j]
        overlap = len(a["topic_tokens"] & b["topic_tokens"])
        if overlap < 3:
            continue
        a_neg = bool(re.search(r"\b(not|never|avoid|don.t|instead|rather|stop)\b", a["decision"], re.I))
        b_neg = bool(re.search(r"\b(not|never|avoid|don.t|instead|rather|stop)\b", b["decision"], re.I))
        if a_neg != b_neg:
            conflicts.append((a["title"], b["title"], overlap))

print(f"Conflict check: {len(entries)} entries, {len(conflicts)} possible contradictions")
for a, b, score in conflicts:
    print(f"  [topic overlap={score}]")
    print(f"    {a.lstrip('#').strip()}")
    print(f"    {b.lstrip('#').strip()}")
if not conflicts:
    print("  No contradictions detected.")
PYEOF2
  ;;

install-hook)
  # Install a pre-commit hook that enforces learning-log entry format.
  git_dir="${2:-.git}"
  hook="$git_dir/hooks/pre-commit"
  cat > "$hook" << 'HOOK'
#!/usr/bin/env bash
# BosskuAI pre-commit: validate learning-log.md entry format
if git diff --cached --name-only | grep -q "learning-log.md"; then
  python3 scripts/rotate_learning_log.py --check --root . 2>&1 | grep -v "^BosskuAI"
  if python3 scripts/rotate_learning_log.py --check --root . 2>&1 | grep -q "Near-duplicates.*[1-9]"; then
    echo "WARNING: near-duplicate entries detected in learning-log.md"
    echo "Run: python3 scripts/rotate_learning_log.py --check to review"
  fi
fi
exit 0
HOOK
  chmod +x "$hook"
  echo "Pre-commit hook installed at $hook"
  ;;

help|--help|-h)
  echo "Usage: bash scripts/team_memory.sh <command> [options]"
  echo "Commands:"
  echo "  tag-author [name]           Print author tag for next learning-log entry"
  echo "  merge-logs <a> <b> [out]    Merge two learning-log.md files chronologically"
  echo "  check-conflicts [log-path]  Detect contradicting entries"
  echo "  install-hook [.git-dir]     Install pre-commit hook for format validation"
  ;;

*)
  echo "Unknown command: $cmd" >&2; exit 1 ;;
esac
