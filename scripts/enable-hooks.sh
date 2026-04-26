#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
src="$root/.claude/settings.hooks.example.json"
dest="$root/.claude/settings.json"
if [[ ! -f "$src" ]]; then
  echo "Missing hook example config: $src" >&2
  exit 1
fi
cp "$src" "$dest"
echo "BosskuAI advisory hooks enabled in .claude/settings.json"
