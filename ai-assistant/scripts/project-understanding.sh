#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./ai-assistant/scripts/project-understanding.sh [workspace-root] [--write-active-continuation]

Prepare a safe refresh packet for re-learning the current project understanding
without wiping existing BosskuAI memory.

Behavior:
  - snapshots ai-assistant/memory/project-understanding.md
  - snapshots ai-assistant/memory/agent-profile.md
  - writes a timestamped refresh prompt into .bosskuai-backups/
  - optionally writes the refresh task into ai-assistant/memory/active-continuation.md
EOF
}

workspace_root=""
write_active_continuation=0

for arg in "$@"; do
  case "$arg" in
    --write-active-continuation)
      write_active_continuation=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      if [[ -n "$workspace_root" ]]; then
        echo "Error: multiple workspace roots provided" >&2
        usage
        exit 1
      fi
      workspace_root="$arg"
      ;;
  esac
done

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
default_root="$script_dir/../.."
workspace_root="${workspace_root:-$default_root}"
workspace_root="$(cd "$workspace_root" && pwd)"

memory_dir="$workspace_root/ai-assistant/memory"
project_understanding="$memory_dir/project-understanding.md"
agent_profile="$memory_dir/agent-profile.md"
active_continuation="$memory_dir/active-continuation.md"

if [[ ! -f "$project_understanding" ]]; then
  echo "Error: expected project understanding file at $project_understanding" >&2
  exit 1
fi

if [[ ! -f "$agent_profile" ]]; then
  echo "Error: expected agent profile file at $agent_profile" >&2
  exit 1
fi

timestamp="$(date '+%Y%m%d-%H%M%S')"
backup_root="$workspace_root/.bosskuai-backups/project-understanding-refresh/$timestamp"
mkdir -p "$backup_root"

snapshot_project_understanding="$backup_root/project-understanding.before-refresh.md"
snapshot_agent_profile="$backup_root/agent-profile.snapshot.md"
refresh_prompt_file="$backup_root/refresh-prompt.txt"

cp "$project_understanding" "$snapshot_project_understanding"
cp "$agent_profile" "$snapshot_agent_profile"

cat >"$refresh_prompt_file" <<EOF
Use bosskuai-project-understanding and bosskuai-codebase-analysis.

Refresh the current workspace understanding from source.

Rules:
- Treat ai-assistant/memory/project-understanding.md as a stale baseline, not the source of truth.
- Compare against this snapshot only to preserve still-correct durable facts:
  $snapshot_project_understanding
- Preserve other memory files. Do not wipe agent-profile.md, learning-log.md, bug-patterns.md, or market-notes.md.
- Update ai-assistant/memory/project-understanding.md in place.
- If repo evidence materially changes who this project serves or how it should be operated, propose a minimal update to ai-assistant/memory/agent-profile.md too.
- Separate confirmed facts, inference, and unknowns.

Deliver:
1. refreshed project summary
2. refreshed architecture and codebase analysis
3. key source-of-truth files and execution paths
4. changes since the previous snapshot
5. exact update for ai-assistant/memory/project-understanding.md
6. any minimal follow-up update needed in ai-assistant/memory/agent-profile.md or ai-assistant/memory/learning-log.md
EOF

if (( write_active_continuation == 1 )); then
  cat >"$active_continuation" <<EOF
# Active continuation (ephemeral)

Use this file only when work must pause because context is nearly full, usage is tight, or you are switching tools/sessions to finish the same task.

## Last updated

- **Date:** $timestamp
- **Tool:** BosskuAI project-understanding script
- **Reason:** refresh workspace understanding after repo changes
- **Phase:** plan

## Goal

Refresh ai-assistant/memory/project-understanding.md from current repo evidence without wiping durable memory.

## Completed

- Backed up the previous project-understanding snapshot to:
  - $snapshot_project_understanding
- Backed up the current agent-profile snapshot to:
  - $snapshot_agent_profile
- Wrote a ready-to-paste refresh prompt to:
  - $refresh_prompt_file

## Remaining

- Re-run bosskuai-project-understanding and bosskuai-codebase-analysis from source.
- Update ai-assistant/memory/project-understanding.md in place.
- Propose any minimal agent-profile or learning-log update only if repo evidence changed materially.

## Next action

Paste the refresh prompt from $refresh_prompt_file into the current AI tool.
EOF
fi

echo "BosskuAI project-understanding refresh packet created"
echo "Workspace: $workspace_root"
echo "Snapshot: $snapshot_project_understanding"
echo "Agent profile snapshot: $snapshot_agent_profile"
echo "Prompt file: $refresh_prompt_file"
if (( write_active_continuation == 1 )); then
  echo "Active continuation updated: $active_continuation"
else
  echo "Active continuation unchanged (use --write-active-continuation to update it)."
fi
echo
echo "Next step:"
echo "  Paste the prompt below into Codex, Claude, or Cursor."
echo
cat "$refresh_prompt_file"
