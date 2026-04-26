#!/usr/bin/env bash

# Advisory hook: fires on PreToolUse for Edit/Write tools.
# Tracks edit count and suggests /compact every 15 edits.
# Always exits 0 — never blocks.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input

COUNTER_FILE="/tmp/bossku_edit_count"

# Read current count, default 0
count=0
if [[ -f "$COUNTER_FILE" ]]; then
  count="$(cat "$COUNTER_FILE" 2>/dev/null || echo 0)"
fi

# Increment
count=$((count + 1))
echo "$count" > "$COUNTER_FILE"

# Every 15 edits, emit a reminder
if (( count % 15 == 0 )); then
  {
    echo "── BosskuAI Context Tip ──────────────────────────────────"
    echo "  📝 ~${count} file edits this session."
    echo "     Consider /compact to compress context, or save a"
    echo "     checkpoint in ai-assistant/memory/ before continuing."
    echo "─────────────────────────────────────────────────────────"
  } >&2
fi

write_hook_output
exit 0
