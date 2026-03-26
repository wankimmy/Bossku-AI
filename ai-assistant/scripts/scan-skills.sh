#!/usr/bin/env bash

set -euo pipefail

repo_root="${1:-.}"
repo_root="$(cd "$repo_root" && pwd)"
skills_dir="$repo_root/ai-assistant/skills"

if [[ ! -d "$skills_dir" ]]; then
  echo "Error: skills directory not found: $skills_dir" >&2
  exit 1
fi

extract_field() {
  local file="$1"
  local field="$2"
  awk -v f="$field" '
    BEGIN { fm=0 }
    /^---$/ { fm++; next }
    fm==1 {
      n = length(f) + 2
      if (substr($0, 1, n) == f ": ") {
        val = substr($0, n+1)
        gsub(/^"/, "", val)
        gsub(/"$/, "", val)
        print val
        exit
      }
    }
    fm>=2 { exit }
  ' "$file"
}

files=()
while IFS= read -r file; do
  files+=("$file")
done < <(find "$skills_dir" -name 'SKILL.md' -type f | sort)

echo "BosskuAI Skill Inventory"
echo "Repo: $repo_root"
echo "Total skills: ${#files[@]}"
echo
echo "| Skill | Path | Description |"
echo "|---|---|---|"

for file in "${files[@]}"; do
  name="$(extract_field "$file" "name")"
  description="$(extract_field "$file" "description")"
  rel="${file#"$repo_root"/}"
  safe_desc="${description//|/\\/}"
  echo "| ${name:-"(missing name)"} | $rel | ${safe_desc:-"(missing description)"} |"
done
