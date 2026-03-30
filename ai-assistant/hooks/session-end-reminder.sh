#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI hook] Session end reminder"
  echo "- Do not auto-write memory or rules just because a hook fired."
  echo "- Review ai-assistant/references/checklists/learning-promotion-checklist.md before promoting lessons."
  echo "- If the task was meaningful, consider a concise update to ai-assistant/memory/learning-log.md or session handoff."
  echo "- For a manual freshness pass, run: bash ./ai-assistant/scripts/learning-doctor.sh"
  echo "- Current changed files:"
  git_changed_files_summary "$repo_root"
} >&2

write_hook_output
