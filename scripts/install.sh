#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/install.sh <target-dir> [--force] [--skip-check] [--preserve-memory] [--skills-only]

Install the BosskuAI workspace layer into an existing project workspace.

Installed entries (full install, default):
  AGENTS.md
  CLAUDE.md
  WORKSPACE-ONBOARDING.md
  .codex/
  .claude/
  .cursor/
  ai-assistant/

Options:
  --force
      Moves conflicting entries into a timestamped backup folder, then copies the layer.
  --preserve-memory
      Before replacing ai-assistant/, saves ai-assistant/memory/ and restores it after install.
      Use with full install to keep project-specific memory without a manual restore step.
  --skills-only
      Copies only ai-assistant/skills/, ai-assistant/references/, and ai-assistant/scripts/
      from the starter. Does not change AGENTS.md, tool configs (.cursor/.claude/.codex),
      or ai-assistant/memory/. Implies no full-layer conflict checks (--force not used).
  --skip-check
      Skips ./scripts/check-workspace.sh after install.

Behavior:
  - Refuses to overwrite existing entries by default (full install only)
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
preserve_memory=0
skills_only=0
target_dir=""

for arg in "$@"; do
  case "$arg" in
    --force)
      force=1
      ;;
    --skip-check)
      skip_check=1
      ;;
    --preserve-memory)
      preserve_memory=1
      ;;
    --skills-only)
      skills_only=1
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

if (( skills_only && preserve_memory )); then
  echo "Note: --skills-only does not replace ai-assistant/memory; --preserve-memory is redundant." >&2
fi

if (( skills_only )); then
  assistant_dir="$target_dir/ai-assistant"
  mkdir -p "$assistant_dir"
  for sub in skills references scripts; do
    src_sub="$repo_root/ai-assistant/$sub"
    dest_sub="$assistant_dir/$sub"
    if [[ ! -e "$src_sub" ]]; then
      echo "Error: missing source path in starter: $src_sub" >&2
      exit 1
    fi
    rm -rf "$dest_sub"
    cp -R "$src_sub" "$dest_sub"
  done
  echo "BosskuAI skills layer (skills + references + scripts) installed under: $assistant_dir"
  if (( skip_check == 0 )); then
    echo
    "$script_dir/check-workspace.sh" "$target_dir"
  else
    echo "Skipped workspace check (--skip-check). Run: ./scripts/check-workspace.sh \"$target_dir\""
  fi
  exit 0
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

memory_stash=""
had_memory_to_preserve=0
if (( preserve_memory )) && [[ -d "$target_dir/ai-assistant/memory" ]]; then
  memory_stash="$(mktemp -d "${TMPDIR:-/tmp}/bosskuai-memory-stash.XXXXXX")"
  cp -a "$target_dir/ai-assistant/memory/." "$memory_stash/"
  had_memory_to_preserve=1
  echo "Preserved existing ai-assistant/memory into temporary stash (will restore after install)."
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

if (( had_memory_to_preserve )); then
  mkdir -p "$target_dir/ai-assistant/memory"
  cp -a "$memory_stash/." "$target_dir/ai-assistant/memory/"
  rm -rf "$memory_stash"
  echo "Restored preserved ai-assistant/memory/ over the new layer."
fi

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
