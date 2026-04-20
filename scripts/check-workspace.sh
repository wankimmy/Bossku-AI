#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/check-workspace.sh [target-dir] [--profile auto|full|skills-only]

Validate that a workspace has the expected BosskuAI layer installed.
Defaults to the current directory and auto-detects the install profile.
EOF
}

target_dir="."
profile="auto"

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    --profile)
      profile="${2:-auto}"
      shift 2
      ;;
    --profile=*)
      profile="${1#--profile=}"
      shift
      ;;
    *)
      target_dir="$1"
      shift
      ;;
  esac
done

target_dir="$(cd "$target_dir" && pwd)"

detect_profile() {
  if [[ "$profile" != "auto" ]]; then
    echo "$profile"
    return
  fi

  if [[ -e "$target_dir/AGENTS.md" || -d "$target_dir/.claude" || -d "$target_dir/.codex" ]]; then
    echo "full"
    return
  fi

  if [[ -d "$target_dir/ai-assistant/skills" ]]; then
    echo "skills-only"
    return
  fi

  echo "full"
}

profile="$(detect_profile)"

full_required_paths=(
  "AGENTS.md"
  "CLAUDE.md"
  "WORKSPACE-ONBOARDING.md"
  ".codex/AGENTS.md"
  ".codex/config.toml"
  ".claude/rules/bosskuai.md"
  ".cursor/rules/bosskuai.mdc"
  ".claude/commands/plan.md"
  ".claude/commands/verify.md"
  ".claude/commands/quality-gate.md"
  ".claude/commands/skill-stocktake.md"
  ".claude/commands/rules-distill.md"
  "ai-assistant/memory/agent-profile.md"
  "ai-assistant/memory/project-understanding.md"
  "ai-assistant/memory/plan-log.md"
  "ai-assistant/memory/vector-config.json"
  "ai-assistant/skills/bosskuai-workspace-assistant/SKILL.md"
  "ai-assistant/skills/bosskuai-search-first/SKILL.md"
  "ai-assistant/skills/bosskuai-skill-stocktake/SKILL.md"
  "ai-assistant/skills/bosskuai-rules-distill/SKILL.md"
  "ai-assistant/references/checklists/verification-checklist.md"
  "ai-assistant/references/checklists/skill-health-checklist.md"
  "ai-assistant/references/checklists/rule-distillation-checklist.md"
  "ai-assistant/scripts/project-understanding.sh"
  "ai-assistant/scripts/vector_memory.py"
  "ai-assistant/hooks/common.sh"
  "ai-assistant/hooks/session-start-reminder.sh"
  "ai-assistant/hooks/pre-compact-reminder.sh"
  "ai-assistant/hooks/session-end-reminder.sh"
)

skills_only_required_paths=(
  "ai-assistant/skills"
  "ai-assistant/references"
  "ai-assistant/scripts"
  "ai-assistant/scripts/vector_memory.py"
)

full_optional_paths=(
  "ai-assistant/memory/active-continuation.md"
)

case "$profile" in
  full)
    required_paths=("${full_required_paths[@]}")
    ;;
  skills-only)
    required_paths=("${skills_only_required_paths[@]}")
    ;;
  *)
    echo "Error: unknown profile '$profile'. Use auto, full, or skills-only." >&2
    exit 2
    ;;
esac

missing=()
for path in "${required_paths[@]}"; do
  if [[ ! -e "$target_dir/$path" ]]; then
    missing+=("$path")
  fi
done

optional_missing=()
if [[ "$profile" == "full" ]]; then
  for path in "${full_optional_paths[@]}"; do
    if [[ ! -e "$target_dir/$path" ]]; then
      optional_missing+=("$path")
    fi
  done
fi

echo "BosskuAI workspace check"
echo "Target: $target_dir"
echo "Profile: $profile"
echo

if (( ${#missing[@]} > 0 )); then
  echo "Status: FAIL"
  echo "Missing entries:"
  for path in "${missing[@]}"; do
    echo "  - $path"
  done
  exit 1
fi

echo "Status: PASS"
echo "Expected workspace files are present."
if (( ${#optional_missing[@]} > 0 )); then
  echo
  echo "Optional starter files not present:"
  for path in "${optional_missing[@]}"; do
    echo "  - $path"
  done
  echo "    This is allowed. BosskuAI treats active continuation as ephemeral handoff state."
fi
echo
echo "Optional integrity checks:"
echo "  ./scripts/verify-skill-references.sh   # skill SKILL.md → references/ paths"
echo "  ./scripts/validate-skill-index.sh      # skill-index.json ↔ skill folders"
echo "  python3 ./scripts/eval_workspace.py    # prompt surface, routing-fit, retrieval relevance"
echo
echo "Recommended next step:"
if [[ "$profile" == "skills-only" ]]; then
  echo "  Open the target repo and verify the installed skills and references match your local workflow."
else
  echo "  Open this workspace root in Codex, Claude, or Cursor and run the onboarding prompt in WORKSPACE-ONBOARDING.md"
fi
