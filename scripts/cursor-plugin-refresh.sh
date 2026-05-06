#!/usr/bin/env bash
# Drop Cursor caches for BosskuAI so Skills are re-read from disk after git pull / skill adds.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Skill packages (SKILL.md under ai-assistant/skills):" \
  "$(find "${ROOT}/ai-assistant/skills" -mindepth 2 -maxdepth 2 -name SKILL.md 2>/dev/null | wc -l | tr -d ' ')"

STALE=(
  "${HOME}/.cursor/plugins/marketplaces/github.com/wankimmy/bossku-ai"
  "${HOME}/.cursor/plugins/cache/bosskuai-marketplace"
)

for path in "${STALE[@]}"; do
  if [[ -e "$path" ]]; then
    echo "Removing: $path"
    rm -rf "$path"
  fi
done

echo "Done. Quit Cursor completely and reopen (or Cmd+Shift+P → Developer: Reload Window)."
echo "Local plugin path should symlink repo root:"
echo "  ln -sf ${ROOT} ~/.cursor/plugins/local/bossku-ai"
