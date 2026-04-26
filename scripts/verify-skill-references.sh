#!/usr/bin/env bash
# Verify that paths under ../../references/ cited in ai-assistant/skills/**/SKILL.md exist.
# Run from BosskuAI repo root: ./scripts/verify-skill-references.sh

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
skills_dir="$root/ai-assistant/skills"
refs_root="$root/ai-assistant/references"

if [[ ! -d "$skills_dir" ]]; then
  echo "error: expected skills directory at $skills_dir" >&2
  exit 1
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

grep -Rho '\.\./\.\./references/[^`)\]*' "$skills_dir" --include='SKILL.md' 2>/dev/null \
  | sed 's|\.\./\.\./references/||' \
  | sed 's/[[:space:]]*$//' \
  | grep -E '\.(md|MD)$' \
  | sort -u >"$tmp" || true

count="$(wc -l <"$tmp" | tr -d ' ')"
if [[ "$count" == "0" ]]; then
  echo "No ../../references/ paths found in SKILL.md files."
  exit 0
fi

missing=0
while IFS= read -r r; do
  [[ -z "$r" ]] && continue
  if [[ "$r" == *".."* || "$r" == /* ]]; then
    echo "SKIP suspicious path: $r"
    continue
  fi
  if [[ ! -f "$refs_root/$r" ]]; then
    echo "MISSING: $r"
    missing=1
  fi
done <"$tmp"

echo "Skill reference verification"
echo "Root: $root"
echo "Unique references checked: $count"
echo

if [[ "$missing" -ne 0 ]]; then
  echo "FAIL — one or more referenced files are missing under ai-assistant/references/"
  exit 1
fi

echo "PASS — all referenced files exist."
