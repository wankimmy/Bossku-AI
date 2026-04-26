#!/usr/bin/env bash
# validate_changelog.sh — CI gate: ensure skill-index.json and install.sh stay consistent.
#
# Checks:
#   1. No deprecated_alias skill is referenced in install.sh profile arrays.
#   2. No skill referenced in CHANGELOG as "Added" is now a deprecated_alias
#      without a corresponding "Deprecated" note in the same or later entry.
#   3. All skill folder names exist in skill-index.json (orphaned folders).
#
# Usage:
#   bash scripts/validate_changelog.sh [root-dir]
#   Returns exit code 1 if any check fails.

set -euo pipefail

root="${1:-.}"
root="$(cd "$root" && pwd)"
index="$root/skill-index.json"
install="$root/scripts/install.sh"
changelog="$root/CHANGELOG.md"
skills_dir="$root/ai-assistant/skills"

fail=0

echo "BosskuAI changelog/index consistency check"
echo "Root: $root"
echo ""

# 1. Deprecated aliases must not appear in install.sh profile arrays
echo "Check 1: deprecated aliases not in install.sh profiles"
deprecated_ids=$(python3 -c "
import json
idx = json.load(open('$index'))
for s in idx['skills']:
    if s.get('status') == 'deprecated_alias':
        print(s['id'])
")

found_in_install=0
while IFS= read -r dep_id; do
  if grep -qE "\\b${dep_id}\\b" "$install"; then
    echo "  FAIL: '$dep_id' (deprecated_alias) is still referenced in install.sh"
    found_in_install=1
    fail=1
  fi
done <<< "$deprecated_ids"
[ "$found_in_install" -eq 0 ] && echo "  PASS"

# 2. Skills in CHANGELOG "Added" sections that are now deprecated_aliases
echo ""
echo "Check 2: CHANGELOG 'Added' skills not silently deprecated"
added_in_changelog=$(grep -oE 'bosskuai-[a-z-]+' "$changelog" | sort -u)
mismatch=0
while IFS= read -r skill_id; do
  status=$(python3 -c "
import json
idx = json.load(open('$index'))
entry = next((s for s in idx['skills'] if s['id'] == '$skill_id'), None)
print(entry['status'] if entry else 'not_in_index')
" 2>/dev/null)
  if [[ "$status" == "deprecated_alias" ]]; then
    # Check if CHANGELOG has a "Deprecated" or "removed" note for this skill
    if ! grep -qiE "(deprecated|removed|replaced).*${skill_id}|${skill_id}.*(deprecated|removed|replaced)" "$changelog"; then
      echo "  WARN: '$skill_id' mentioned in CHANGELOG but now deprecated_alias with no deprecation note"
      mismatch=1
    fi
  fi
done <<< "$added_in_changelog"
[ "$mismatch" -eq 0 ] && echo "  PASS"

# 3. Orphaned skill folders (exist on disk but not in index)
echo ""
echo "Check 3: no orphaned skill folders"
orphaned=0
for skill_dir in "$skills_dir"/*/; do
  skill_id=$(basename "$skill_dir")
  [[ "$skill_id" == _* ]] && continue
  in_index=$(python3 -c "
import json
idx = json.load(open('$index'))
print('yes' if any(s['id'] == '$skill_id' for s in idx['skills']) else 'no')
")
  if [[ "$in_index" == "no" ]]; then
    echo "  WARN: skill folder '$skill_id' not in skill-index.json"
    orphaned=1
  fi
done
[ "$orphaned" -eq 0 ] && echo "  PASS"

echo ""
if [ "$fail" -eq 0 ]; then
  echo "Status: PASS"
else
  echo "Status: FAIL — fix issues above before merging"
  exit 1
fi
