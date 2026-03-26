#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI hook] Session start reminder"
  echo "- Treat AGENTS.md as the workspace source of truth."
  echo "- If repo context is unclear, use bosskuai-project-understanding first."
  echo "- For meaningful tasks, plan before execution."
  echo "- Shared memory lives under ai-assistant/memory/ and should only be updated deliberately."
} >&2

write_hook_output
