#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

{
  echo "[BosskuAI] Auto-enforce — emit [TASK START] before responding:"
  echo "  1. READ  ${repo_root}/ai-assistant/memory/ (continuation→profile→understanding→log)"
  echo "  2. ROUTE intent→cluster→skill via ${repo_root}/AGENTS.md Quick reference"
  echo "  3. LOAD  ${repo_root}/ai-assistant/skills/bosskuai-<name>/SKILL.md"
  echo "  4. EMIT  [TASK START] header (trivial tasks: all fields = 'trivial')"
  echo "  5. PLAN  with Opus 4.6 (Claude) / gpt-5.4 (Codex) / strongest model (Cursor)"
  echo ""
  echo "  Cluster → skill quick map:"
  echo "  engineering→engineering-delivery | security→cybersecurity-risk | product→product-strategy"
  echo "  quality→rigorous-code-review | architecture→software-architecture | growth→marketing-growth"
  echo "  understand→project-understanding | review→business-logic-review | ux→ui-ux-design-to-code"
} >&2

write_hook_output
