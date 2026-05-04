#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI] Post-response — verify before done:"
  echo "  □ Frontier audit completed for meaningful work?"
  echo "  □ DoD checklist passed? (CLAUDE.md / AGENTS.md)"
  echo "  □ Durable plan stored in plan-log.md when useful?"
  echo "  □ Durable outcome stored in learning-log.md / durable-memory.md when useful?"
  echo "  □ Vector DB synced with auto_memory.py sync?"
  echo "  □ Open risks and skipped checks stated?"
  echo "  Changed files:"
  git_changed_files_summary "$repo_root"
} >&2

write_hook_output
