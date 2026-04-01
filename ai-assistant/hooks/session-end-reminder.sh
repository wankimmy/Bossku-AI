#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI] Post-response — verify before done:"
  echo "  □ [TASK END] block emitted? (Meaningful/Memory/Learning fields)"
  echo "  □ DoD checklist passed? (CLAUDE.md)"
  echo "  □ Memory updated OR 'Memory: unchanged (trivial)' stated?"
  echo "  □ Meaningful:yes → continuous-learning run or deferred with reason?"
  echo "  Changed files:"
  git_changed_files_summary "$repo_root"
} >&2

write_hook_output
