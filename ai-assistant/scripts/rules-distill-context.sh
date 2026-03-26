#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="${1:-$script_dir/../..}"
repo_root="$(cd "$repo_root" && pwd)"

echo "# BosskuAI Rules Distillation Context"
echo
"$script_dir/scan-rules.sh" "$repo_root"
echo
"$script_dir/scan-skills.sh" "$repo_root"
echo
echo "BosskuAI Reference Inventory"
echo "Repo: $repo_root"
echo
echo "| Reference Type | Count |"
echo "|---|---|"
echo "| Checklists | $(find "$repo_root/ai-assistant/references/checklists" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ') |"
echo "| Playbooks | $(find "$repo_root/ai-assistant/references/playbooks" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ') |"
echo "| Pitfall files | $(find "$repo_root/ai-assistant/references/pitfalls" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ') |"
