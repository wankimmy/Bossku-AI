#!/usr/bin/env bash

set -euo pipefail

repo_root="${1:-.}"
repo_root="$(cd "$repo_root" && pwd)"

paths=(
  "$repo_root/AGENTS.md"
  "$repo_root/CLAUDE.md"
  "$repo_root/.codex/AGENTS.md"
)

while IFS= read -r file; do
  paths+=("$file")
done < <(find "$repo_root/.claude/rules" -name '*.md' -type f 2>/dev/null | sort)

while IFS= read -r file; do
  paths+=("$file")
done < <(find "$repo_root/.cursor/rules" -name '*.mdc' -type f 2>/dev/null | sort)

files=()
for path in "${paths[@]}"; do
  [[ -f "$path" ]] && files+=("$path")
done

echo "BosskuAI Rule Inventory"
echo "Repo: $repo_root"
echo "Total rule files: ${#files[@]}"
echo

for file in "${files[@]}"; do
  rel="${file#"$repo_root"/}"
  echo "## $rel"
  headings="$(grep -E '^## ' "$file" | sed 's/^## //')"
  if [[ -n "$headings" ]]; then
    echo "Sections:"
    while IFS= read -r heading; do
      echo "- $heading"
    done <<< "$headings"
  else
    echo "Sections:"
    echo "- (no level-2 headings)"
  fi
  echo
done
