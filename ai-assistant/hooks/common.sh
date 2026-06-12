#!/usr/bin/env bash

set -euo pipefail

HOOK_INPUT=""

read_hook_input() {
  if [[ ! -t 0 ]]; then
    HOOK_INPUT="$(cat)"
  fi
}

# Intentionally a no-op. Hook stdin must NOT be echoed back: on context-injecting
# events (SessionStart/UserPromptSubmit) stdout becomes model context, and on all
# events it is at best noise. Kept so existing scripts stay call-compatible.
write_hook_output() {
  :
}

resolve_repo_root() {
  local candidate="${1:-.}"
  (cd "$candidate" && pwd)
}

# Resolve the BosskuAI home (workspace layer with ai-assistant/memory) so hooks
# work from any project, not just the Bossku-AI repo itself. Order:
#   1. $BOSSKU_HOME when it points at a valid workspace layer
#   2. the current project, when it carries its own ai-assistant/memory
#   3. a sibling Bossku-AI checkout next to the current project
#   4. the plugin/checkout root these hooks ship in (read-only fallback)
resolve_bossku_home() {
  local project="${CLAUDE_PROJECT_DIR:-$PWD}"
  if [[ -n "${BOSSKU_HOME:-}" && -d "${BOSSKU_HOME}/ai-assistant/memory" ]]; then
    echo "$BOSSKU_HOME"
    return
  fi
  if [[ -d "$project/ai-assistant/memory" ]]; then
    echo "$project"
    return
  fi
  local sibling
  sibling="$(dirname "$project")/Bossku-AI"
  if [[ -d "$sibling/ai-assistant/memory" ]]; then
    echo "$sibling"
    return
  fi
  (cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
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
