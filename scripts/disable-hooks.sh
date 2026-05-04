#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cat > "$root/.claude/settings.json" <<'JSON'
{
  "bosskuai": {
    "hooks": "disabled-by-default",
    "note": "Run scripts/enable-hooks.sh or scripts/enable-hooks.ps1 to enable advisory Claude Code hooks with auto memory capture."
  }
}
JSON
echo "BosskuAI advisory hooks disabled in .claude/settings.json"
