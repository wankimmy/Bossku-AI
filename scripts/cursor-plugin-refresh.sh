#!/usr/bin/env bash
# Cursor: rescan BosskuAI skills after git pull, and reinstall the *local* plugin layout Cursor expects.
#
# Marketplace Git pin can still fail (see Cursor Plugins.log); use local ~/.cursor/plugins/local/bossku-ai.
#
# Optional: BOSSKU_CURSOR_NUKE_CACHES=1 removes Cursor's bossku-ai marketplace clones/caches only.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL="${HOME}/.cursor/plugins/local/bossku-ai"
HASH_MARKET="41289be0da4f3bab8c2c49d509e5aa9aaa120fc7"
CACHE_MP="${HOME}/.cursor/plugins/cache/bosskuai-marketplace/bossku-ai/${HASH_MARKET}"

echo "Skill packages (SKILL.md under ai-assistant/skills):" \
  "$(find "${ROOT}/ai-assistant/skills" -mindepth 2 -maxdepth 2 -name SKILL.md 2>/dev/null | wc -l | tr -d ' ')"

python3 - "${ROOT}" << 'PY'
import json, pathlib, sys
root = pathlib.Path(sys.argv[1])
idx = json.load(open(root / "skill-index.json"))
n = len(idx["skills"])
m = len(idx["routing"].get("manual_only_skill_ids", []))
print(f"skill-index.json: {n} skills, {m} manual-only routing (often ~{n - m} visible in Cursor UI)")
PY

# Root plugin.json keeps local + tooling aligned with .cursor-plugin/plugin.json (overwrite; avoids cp same-file errors).
install -m 644 "${ROOT}/.cursor-plugin/plugin.json" "${ROOT}/plugin.json"

# Cursor often skips symlink-only plugin dirs; use a small real dir + symlinked trees.
rm -rf "${LOCAL}"
mkdir -p "${LOCAL}"
cp "${ROOT}/.cursor-plugin/plugin.json" "${LOCAL}/plugin.json"
for name in ai-assistant agents rules .cursor; do
  ln -sfn "${ROOT}/${name}" "${LOCAL}/${name}"
done
ln -sfn "${ROOT}/.cursor-plugin" "${LOCAL}/.cursor-plugin"
echo "Local Cursor plugin rebuilt at ${LOCAL}"

if [[ "${BOSSKU_CURSOR_NUKE_CACHES:-}" == "1" ]]; then
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
fi

# If marketplace still installs this broken SHA, pointing the cache slot at THIS checkout avoids empty manifest trees.
mkdir -p "$(dirname "${CACHE_MP}")"
rm -rf "${CACHE_MP}"
ln -sfn "${ROOT}" "${CACHE_MP}"
echo "Symlinked marketplace cache slot → repo: ${CACHE_MP}"

echo ""
echo "Done. Quit Cursor completely and reopen (or Cmd+Shift+P → Developer: Reload Window)."
echo "Turn off Bossku in Cursor cloud Plugins if UI still shows marketplace errors;"
echo "  local install should win once Cursor rescans ~/.cursor/plugins/local/bossku-ai."
echo ""
echo "To also delete marketplace git clones/cache dirs: BOSSKU_CURSOR_NUKE_CACHES=1 $0"
