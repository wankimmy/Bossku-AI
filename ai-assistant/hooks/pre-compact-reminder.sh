#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI hook] Pre-compact reminder"
  echo "- Save a compact handoff before losing context."
  echo "- Use ai-assistant/references/session-handoff-template.md."
  echo "- Separate verified work from assumptions and follow-up."
  echo "- Current changed files:"
  git_changed_files_summary "$repo_root"
} >&2

write_hook_output
