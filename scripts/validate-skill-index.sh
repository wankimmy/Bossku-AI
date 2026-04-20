#!/usr/bin/env bash
# Validate consistency between ai-assistant/skills/ folders and skill-index.json.
# Run from BosskuAI repo root: ./scripts/validate-skill-index.sh [target-dir]
#
# Checks:
#   1. Every indexed skill in skill-index.json has a folder with SKILL.md
#   2. No orphan skill folders exist without an index entry
#   3. Required memory files are present and optional starter memory is noted
#   4. Deprecated aliases point to a real replacement skill
#   5. Core routing skills exist in the index and on disk
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
echo "── Check 3: memory files ────────────────────────────────────────────────"

# ── 3. Required memory files ─────────────────────────────────────────────────
required_memory=(
  "agent-profile.md"
  "project-understanding.md"
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

optional_memory=(
  "active-continuation.md"
)
for mf in "${optional_memory[@]}"; do
  if [[ -f "$memory_dir/$mf" ]]; then
    check "memory/$mf (optional starter)" "PASS"
  else
    echo "  NOTE  memory/$mf (optional starter): missing"
  fi
done

echo
echo "── Check 4: alias replacements are valid ────────────────────────────────"

while IFS=$'\t' read -r alias_id replacement_id; do
  [[ -z "$alias_id" ]] && continue
  if [[ -z "$replacement_id" ]]; then
    check "$alias_id" "FAIL" "deprecated alias is missing replaced_by"
    continue
  fi
  if [[ ! -f "$skills_dir/$replacement_id/SKILL.md" ]]; then
    check "$alias_id" "FAIL" "replacement skill missing: $replacement_id"
  else
    check "$alias_id -> $replacement_id" "PASS"
  fi
done < <(python3 - <<PY
import json
with open("$index_file", "r", encoding="utf-8") as f:
    data = json.load(f)
for skill in data.get("skills", []):
    if skill.get("status") == "deprecated_alias":
        print(f"{skill['id']}\t{skill.get('replaced_by','')}")
PY
)

echo
echo "── Check 5: core routing skills are valid ───────────────────────────────"

while IFS= read -r core_id; do
  [[ -z "$core_id" ]] && continue
  if [[ ! -f "$skills_dir/$core_id/SKILL.md" ]]; then
    check "$core_id" "FAIL" "listed as core in routing.core_skill_ids but missing on disk"
  else
    check "$core_id" "PASS"
  fi
done < <(python3 - <<PY
import json
with open("$index_file", "r", encoding="utf-8") as f:
    data = json.load(f)
for core_id in data.get("routing", {}).get("core_skill_ids", []):
    print(core_id)
PY
)

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
