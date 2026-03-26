#!/usr/bin/env bash

set -euo pipefail

repo_root="${1:-.}"
repo_root="$(cd "$repo_root" && pwd)"
commands_dir="$repo_root/.claude/commands"

if [[ ! -d "$commands_dir" ]]; then
  echo "Error: commands directory not found: $commands_dir" >&2
  exit 1
fi

files=()
while IFS= read -r file; do
  files+=("$file")
done < <(find "$commands_dir" -name '*.md' -type f | sort)

echo "BosskuAI Command Inventory"
echo "Repo: $repo_root"
echo "Total commands: ${#files[@]}"
echo
echo "| Command | Path | Title |"
echo "|---|---|---|"

for file in "${files[@]}"; do
  rel="${file#"$repo_root"/}"
  command_name="$(basename "$file" .md)"
  title="$(grep -m1 '^# ' "$file" | sed 's/^# //')"
  safe_title="${title//|/\\/}"
  echo "| $command_name | $rel | ${safe_title:-"(missing title)"} |"
done
