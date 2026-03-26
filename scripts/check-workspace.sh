#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/check-workspace.sh [target-dir]

Validate that a workspace has the expected BosskuAI layer installed.
Defaults to the current directory.
EOF
}

target_dir="${1:-.}"

if [[ "${target_dir}" == "-h" || "${target_dir}" == "--help" ]]; then
  usage
  exit 0
fi

target_dir="$(cd "$target_dir" && pwd)"

required_paths=(
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
  "ai-assistant/skills/bosskuai-workspace-assistant/SKILL.md"
  "ai-assistant/skills/bosskuai-search-first/SKILL.md"
  "ai-assistant/skills/bosskuai-skill-stocktake/SKILL.md"
  "ai-assistant/skills/bosskuai-rules-distill/SKILL.md"
  "ai-assistant/references/checklists/verification-checklist.md"
  "ai-assistant/references/checklists/skill-health-checklist.md"
  "ai-assistant/references/checklists/rule-distillation-checklist.md"
  "ai-assistant/scripts/scan-skills.sh"
  "ai-assistant/scripts/scan-commands.sh"
  "ai-assistant/scripts/scan-rules.sh"
  "ai-assistant/scripts/skill-stocktake.sh"
  "ai-assistant/scripts/rules-distill-context.sh"
  "ai-assistant/hooks/README.md"
  "ai-assistant/hooks/common.sh"
  "ai-assistant/hooks/session-start-reminder.sh"
  "ai-assistant/hooks/pre-compact-reminder.sh"
  "ai-assistant/hooks/session-end-reminder.sh"
)

missing=()
for path in "${required_paths[@]}"; do
  if [[ ! -e "$target_dir/$path" ]]; then
    missing+=("$path")
  fi
done

echo "BosskuAI workspace check"
echo "Target: $target_dir"
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
echo "Core workspace files are present."
echo
echo "Recommended next step:"
echo "  Open this workspace root in Codex, Claude, or Cursor and run the onboarding prompt in WORKSPACE-ONBOARDING.md"
