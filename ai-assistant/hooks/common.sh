#!/usr/bin/env bash

set -euo pipefail

HOOK_INPUT=""

read_hook_input() {
  if [[ ! -t 0 ]]; then
    HOOK_INPUT="$(cat)"
  fi
}

write_hook_output() {
  if [[ -n "${HOOK_INPUT:-}" ]]; then
    printf '%s' "$HOOK_INPUT"
  fi
}

resolve_repo_root() {
  local candidate="${1:-.}"
  (cd "$candidate" && pwd)
}

git_changed_files_summary() {
  local repo_root="$1"

  if ! git -C "$repo_root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    return 0
  fi

  local changed
  changed="$(git -C "$repo_root" status --short 2>/dev/null || true)"

  if [[ -z "$changed" ]]; then
    echo "No changed files detected."
    return 0
  fi

  echo "$changed" | sed 's/^/  /'
}
