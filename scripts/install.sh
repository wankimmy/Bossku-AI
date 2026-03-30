#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/install.sh <target-dir> [--force] [--skip-check]

Install the BosskuAI workspace layer into an existing project workspace.

Installed entries:
  AGENTS.md
  CLAUDE.md
  WORKSPACE-ONBOARDING.md
  .codex/
  .claude/
  .cursor/
  ai-assistant/

Behavior:
  - Refuses to overwrite existing entries by default
  - With --force, moves conflicting entries into a timestamped backup folder
  - Runs ./scripts/check-workspace.sh on the target after install unless --skip-check
EOF
}

if [[ $# -lt 1 ]]; then
  usage
  exit 1
fi

force=0
skip_check=0
target_dir=""

for arg in "$@"; do
  case "$arg" in
    --force)
      force=1
      ;;
    --skip-check)
      skip_check=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      if [[ -n "$target_dir" ]]; then
        echo "Error: multiple target directories provided" >&2
        usage
        exit 1
      fi
      target_dir="$arg"
      ;;
  esac
done

if [[ -z "$target_dir" ]]; then
  echo "Error: target directory is required" >&2
  usage
  exit 1
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
target_dir="$(cd "$target_dir" && pwd)"

if [[ ! -d "$target_dir" ]]; then
  echo "Error: target directory does not exist: $target_dir" >&2
  exit 1
fi

entries=(
  "AGENTS.md"
  "CLAUDE.md"
  "WORKSPACE-ONBOARDING.md"
  ".codex"
  ".claude"
  ".cursor"
  "ai-assistant"
)

conflicts=()
for entry in "${entries[@]}"; do
  if [[ -e "$target_dir/$entry" ]]; then
    conflicts+=("$entry")
  fi
done

if (( ${#conflicts[@]} > 0 && force == 0 )); then
  echo "Refusing to overwrite existing target entries:" >&2
  for conflict in "${conflicts[@]}"; do
    echo "  - $conflict" >&2
  done
  echo >&2
  echo "Re-run with --force to back up and replace those entries." >&2
  exit 2
fi

backup_dir=""
if (( ${#conflicts[@]} > 0 && force == 1 )); then
  timestamp="$(date '+%Y%m%d-%H%M%S')"
  backup_dir="$target_dir/.bosskuai-backups/$timestamp"
  mkdir -p "$backup_dir"
  for conflict in "${conflicts[@]}"; do
    mkdir -p "$(dirname "$backup_dir/$conflict")"
    mv "$target_dir/$conflict" "$backup_dir/$conflict"
  done
fi

for entry in "${entries[@]}"; do
  cp -R "$repo_root/$entry" "$target_dir/$entry"
done

echo "BosskuAI workspace layer installed to: $target_dir"
if [[ -n "$backup_dir" ]]; then
  echo "Backed up replaced entries to: $backup_dir"
fi

if (( skip_check == 0 )); then
  echo
  "$script_dir/check-workspace.sh" "$target_dir"
else
  echo "Skipped workspace check (--skip-check). Run: ./scripts/check-workspace.sh \"$target_dir\""
fi
