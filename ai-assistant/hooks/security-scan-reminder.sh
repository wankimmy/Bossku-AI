#!/usr/bin/env bash

# Advisory hook: fires on Stop event.
# Checks if any modified files look sensitive. Emits a security reminder if so.
# Always exits 0 — never blocks.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input
repo_root="$(resolve_repo_root "${1:-$script_dir/../..}")"

# Get list of modified files (git status, or empty if not a git repo)
changed_files=""
if git -C "$repo_root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  changed_files="$(git -C "$repo_root" status --short 2>/dev/null | awk '{print $2}' || true)"
fi

if [[ -z "$changed_files" ]]; then
  write_hook_output
  exit 0
fi

# Check for sensitive file name patterns
sensitive_files="$(echo "$changed_files" | grep -iE '\.(env|secret|credential|key|token|pem|p12|pfx)$|config\.(ts|js|py|json|yaml|yml)$|auth\.(ts|js|py)$' | head -5 || true)"

if [[ -n "$sensitive_files" ]]; then
  {
    echo "── BosskuAI Security Reminder ──────────────────────────"
    echo "  ⚠  Session touched potentially sensitive files:"
    echo "$sensitive_files" | sed 's/^/     /'
    echo ""
    echo "  Before committing:"
    echo "  ✓ No secrets hardcoded or logged"
    echo "  ✓ No credentials in response bodies"
    echo "  ✓ Auth/permissions unchanged or explicitly reviewed"
    echo "────────────────────────────────────────────────────────"
  } >&2
fi

write_hook_output
exit 0
