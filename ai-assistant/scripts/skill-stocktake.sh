#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="${1:-$script_dir/../..}"
repo_root="$(cd "$repo_root" && pwd)"

echo "# BosskuAI Skill Stocktake Context"
echo
"$script_dir/scan-skills.sh" "$repo_root"
echo
"$script_dir/scan-commands.sh" "$repo_root"
echo
echo "BosskuAI Reference Counts"
echo "Repo: $repo_root"
echo
echo "- Checklists: $(find "$repo_root/ai-assistant/references/checklists" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ')"
echo "- Playbooks: $(find "$repo_root/ai-assistant/references/playbooks" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ')"
echo "- Pitfall files: $(find "$repo_root/ai-assistant/references/pitfalls" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ')"
