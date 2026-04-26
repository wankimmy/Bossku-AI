#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI] Auto-enforce — route light, read evidence first:"
  echo "  1. READ  ${repo_root}/ai-assistant/memory/active-continuation.md"
  echo "  2. QUERY ${repo_root}/ai-assistant/memory/semantic-memory.sqlite3 when present"
  echo "  3. ROUTE intent→cluster→skill via ${repo_root}/AGENTS.md Quick reference"
  echo "  4. LOAD  ${repo_root}/ai-assistant/skills/bosskuai-<name>/SKILL.md"
  echo "  5. PLAN  with Opus 4.6 (Claude) / gpt-5.4 (Codex) / strongest model (Cursor)"
  echo "  6. STORE compact plan in plan-log.md if gated, then sync before execution"
  echo ""
  echo "  Cluster → skill quick map:"
  echo "  engineering→engineering-delivery | security→cybersecurity-risk | product→product-strategy"
  echo "  quality→rigorous-code-review | architecture→software-architecture | growth→marketing-growth"
  echo "  understand→project-understanding | review→business-logic-review | ux→ui-ux-design-to-code"
} >&2

write_hook_output
