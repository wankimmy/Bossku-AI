#!/usr/bin/env bash

# Advisory hook: fires when Bash tool is used. If the command looks like a git commit,
# emits pre-commit quality reminders to stderr. Always exits 0 — never blocks.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input

# Check if the tool input / first argument looks like a git commit
tool_input="${1:-$HOOK_INPUT}"

if echo "$tool_input" | grep -q "git commit"; then
  {
    echo "── BosskuAI Pre-Commit Reminder ────────────────────────"
    echo "  ✓ Secrets check: no API_KEY / SECRET / PASSWORD / TOKEN hardcoded?"
    echo "  ✓ Stray console.log / print / fmt.Println removed?"
    echo "  ✓ Commit message: <type>(<scope>): <subject>"
    echo "    Types: feat | fix | chore | docs | refactor | test | perf"
    echo "────────────────────────────────────────────────────────"
  } >&2
fi

write_hook_output
exit 0
