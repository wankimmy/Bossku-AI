#!/usr/bin/env bash
set -euo pipefail

usage() { cat <<'EOF'
Usage:
  ./scripts/check-workspace.sh [target-dir] [--profile auto|core|dev|growth|design|full|skills-only]
EOF
}

target_dir="."; profile="auto"
while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help) usage; exit 0 ;;
    --profile) profile="${2:-auto}"; shift 2 ;;
    --profile=*) profile="${1#--profile=}"; shift ;;
    *) target_dir="$1"; shift ;;
  esac
done

target_dir="$(cd "$target_dir" && pwd)"
if [[ "$profile" == "auto" ]]; then
  if [[ -d "$target_dir/ai-assistant/skills" && ! -e "$target_dir/AGENTS.md" ]]; then profile="skills-only"; else profile="full"; fi
fi
case "$profile" in core|dev|growth|design|full|skills-only) ;; *) echo "Error: unknown profile '$profile'" >&2; exit 2 ;; esac

base_required=(AGENTS.md CLAUDE.md WORKSPACE-ONBOARDING.md skill-index.json .codex/AGENTS.md .cursor/rules/bosskuai.mdc .claude/rules/bosskuai.md ai-assistant/memory/agent-profile.md ai-assistant/memory/project-understanding.md ai-assistant/references ai-assistant/scripts/vector_memory.py)
core_skills=(bosskuai-workspace-assistant bosskuai-project-understanding bosskuai-search-first bosskuai-human-output bosskuai-token-saver bosskuai-ratchet-loop bosskuai-continuous-learning bosskuai-context-limit-continuation)
dev_skills=(bosskuai-engineering-delivery bosskuai-rigorous-code-review bosskuai-bug-finding bosskuai-software-architecture bosskuai-docker bosskuai-vps-docker-deployment bosskuai-laravel-development bosskuai-database-engineering bosskuai-redis-caching-queues bosskuai-integration-testing)
growth_skills=(bosskuai-marketing-growth bosskuai-seo-geo bosskuai-sales-strategy bosskuai-launch-commercialization bosskuai-competitor-intelligence bosskuai-content-calendar)
design_skills=(bosskuai-ui-ux-design-to-code bosskuai-design-systems bosskuai-3d-web-development bosskuai-gsap-animation bosskuai-lenis-smooth-scroll)

required=()
if [[ "$profile" == "skills-only" ]]; then
  required=(ai-assistant/skills ai-assistant/references ai-assistant/scripts/vector_memory.py)
else
  required+=("${base_required[@]}")
  skills=("${core_skills[@]}")
  [[ "$profile" == "dev" || "$profile" == "full" ]] && skills+=("${dev_skills[@]}")
  [[ "$profile" == "growth" || "$profile" == "full" ]] && skills+=("${growth_skills[@]}")
  [[ "$profile" == "design" || "$profile" == "full" ]] && skills+=("${design_skills[@]}")
  if [[ "$profile" == "full" ]]; then
    required+=(agents .claude/commands/plan.md .claude/settings.json .claude-plugin/plugin.json)
  fi
  for s in "${skills[@]}"; do required+=("ai-assistant/skills/$s/SKILL.md"); done
fi

missing=()
for p in "${required[@]}"; do [[ -e "$target_dir/$p" ]] || missing+=("$p"); done

echo "BosskuAI workspace check"
echo "Target: $target_dir"
echo "Profile: $profile"
echo
if (( ${#missing[@]} > 0 )); then
  echo "Status: FAIL"
  printf '  - %s\n' "${missing[@]}"
  exit 1
fi

echo "Status: PASS"
echo "Expected workspace files are present."
echo
echo "Optional checks:"
echo "  bash scripts/verify-skill-references.sh ."
echo "  bash scripts/validate-skill-index.sh .    # full profile only"
echo "  python3 -S scripts/eval_workspace.py"
