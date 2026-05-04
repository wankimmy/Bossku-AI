#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI] Auto-enforce — memory first, plan first, audit last:"
  echo "  1. READ   ${repo_root}/ai-assistant/memory/active-continuation.md when active"
  echo "  2. QUERY  python3 ${repo_root}/ai-assistant/scripts/auto_memory.py query '<task>' --limit 5"
  echo "  3. ROUTE  intent→cluster→skill via ${repo_root}/AGENTS.md"
  echo "  4. PLAN   with frontier model"
  echo "  5. EXEC   with lower-cost model when safe"
  echo "  6. AUDIT  with frontier model"
  echo "  7. STORE  durable plan/outcome with auto_memory.py remember; sync vector DB"
  echo ""
  echo "  Cluster → skill quick map:"
  echo "  memory→permanent-memory-orchestration | engineering→engineering-delivery"
  echo "  security→cybersecurity-risk | quality→rigorous-code-review | architecture→software-architecture"
  echo "  growth→marketing-growth | understand→project-understanding | ux→ui-ux-design-to-code"
} >&2

write_hook_output
