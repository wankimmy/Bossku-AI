#!/usr/bin/env bash
# Validate consistency between ai-assistant/skills/ folders and skill-index.json.
# Run from BosskuAI repo root: ./scripts/validate-skill-index.sh [target-dir]
#
# Checks:
#   1. Every active/deprecated skill in skill-index.json has a folder with SKILL.md
#   2. No orphan skill folders exist without an index entry
#   3. Required memory files are present
#
# Exit codes:
#   0 = PASS (all checks passed)
#   1 = FAIL (one or more checks failed)

set -euo pipefail

target_dir="${1:-.}"
target_dir="$(cd "$target_dir" && pwd)"

skills_dir="$target_dir/ai-assistant/skills"
index_file="$target_dir/skill-index.json"
memory_dir="$target_dir/ai-assistant/memory"

fail=0

# ── Helper ──────────────────────────────────────────────────────────────────
check() {
  local label="$1"
  local result="$2"   # "PASS" or "FAIL"
  local detail="${3:-}"
  if [[ "$result" == "FAIL" ]]; then
    echo "  FAIL  $label${detail:+: $detail}"
    fail=1
  else
    echo "  PASS  $label"
  fi
}

echo "BosskuAI skill-index validation"
echo "Target: $target_dir"
echo

# ── Prerequisite guards ──────────────────────────────────────────────────────
if [[ ! -f "$index_file" ]]; then
  echo "ERROR: skill-index.json not found at $index_file" >&2
  exit 1
fi

if [[ ! -d "$skills_dir" ]]; then
  echo "ERROR: skills directory not found at $skills_dir" >&2
  exit 1
fi

# Check jq or python availability
if command -v python3 &>/dev/null; then
  parse_cmd="python3"
elif command -v jq &>/dev/null; then
  parse_cmd="jq"
else
  echo "ERROR: requires python3 or jq to parse JSON" >&2
  exit 1
fi

# ── 1. Extract indexed skill IDs ─────────────────────────────────────────────
echo "── Check 1: indexed skills have folders ──────────────────────────────────"
indexed_ids=()
while IFS= read -r id; do
  [[ -n "$id" ]] && indexed_ids+=("$id")
done < <(python3 -c "
import json, sys
with open('$index_file') as f:
    data = json.load(f)
for s in data.get('skills', []):
    print(s['id'])
" 2>/dev/null || jq -r '.skills[].id' "$index_file")

for id in "${indexed_ids[@]}"; do
  skill_md="$skills_dir/$id/SKILL.md"
  if [[ ! -f "$skill_md" ]]; then
    check "$id" "FAIL" "no SKILL.md at ai-assistant/skills/$id/SKILL.md"
  else
    check "$id" "PASS"
  fi
done

echo
echo "── Check 2: skill folders are indexed ────────────────────────────────────"

# ── 2. Find orphan folders ───────────────────────────────────────────────────
# Build a set of indexed IDs for quick lookup
indexed_set=()
for id in "${indexed_ids[@]}"; do
  indexed_set+=("$id")
done

orphans=()
while IFS= read -r folder; do
  name="$(basename "$folder")"
  # Skip hidden files/dirs and non-skill entries
  [[ "$name" == .* ]] && continue
  [[ ! -d "$folder" ]] && continue

  found=0
  for id in "${indexed_ids[@]}"; do
    [[ "$id" == "$name" ]] && found=1 && break
  done

  if [[ $found -eq 0 ]]; then
    orphans+=("$name")
  fi
done < <(find "$skills_dir" -maxdepth 1 -mindepth 1)

if (( ${#orphans[@]} > 0 )); then
  for orphan in "${orphans[@]}"; do
    check "$orphan" "FAIL" "folder exists but not in skill-index.json"
  done
else
  check "(no orphan folders)" "PASS"
fi

echo
echo "── Check 3: required memory files ───────────────────────────────────────"

# ── 3. Required memory files ─────────────────────────────────────────────────
required_memory=(
  "agent-profile.md"
  "project-understanding.md"
  "active-continuation.md"
  "plan-log.md"
  "learning-log.md"
  "bug-patterns.md"
  "market-notes.md"
)
for mf in "${required_memory[@]}"; do
  if [[ -f "$memory_dir/$mf" ]]; then
    check "memory/$mf" "PASS"
  else
    check "memory/$mf" "FAIL" "missing"
  fi
done

echo
echo "── Summary ───────────────────────────────────────────────────────────────"
echo "Indexed skills checked : ${#indexed_ids[@]}"
echo "Orphan folders found   : ${#orphans[@]}"

if [[ $fail -eq 0 ]]; then
  echo
  echo "Status: PASS"
  exit 0
else
  echo
  echo "Status: FAIL — fix the issues above and re-run."
  exit 1
fi
