#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./scripts/install.sh <target-dir> [--profile core|dev|growth|design|full] [--force] [--skip-check] [--preserve-memory] [--with-hooks] [--dry-run]
  ./scripts/install.sh <target-dir> [--skills-only|--sync-layer] [--force] [--skip-check] [--preserve-memory] [--dry-run]

Profiles:
  core    Small workspace layer: permanent memory, routing, human-output, token-saver, search-first, ratchet.
  dev     Core + engineering/review/devops/testing skills.
  growth  Core + marketing, SEO, sales, research, launch skills.
  design  Core + UI/UX, design systems, 3D/animation skills.
  full    Everything. Default.

Options:
  --with-hooks       Install Claude Code advisory hooks, including auto memory capture and vector sync.
  --force            Back up conflicting entries, then replace.
  --preserve-memory  Restore existing ai-assistant/memory/ after install.
  --skills-only      Copy only ai-assistant/skills, references, and scripts.
  --sync-layer       Refresh docs/config/skills except ai-assistant/memory/.
  --skip-check       Skip workspace check after install.
  --dry-run          Print what would be installed without making any changes.
EOF
}

force=0; skip_check=0; preserve_memory=0; skills_only=0; sync_layer=0; with_hooks=0; dry_run=0; profile="full"; target_dir=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --force) force=1; shift ;;
    --skip-check) skip_check=1; shift ;;
    --preserve-memory) preserve_memory=1; shift ;;
    --skills-only) skills_only=1; shift ;;
    --sync-layer) sync_layer=1; shift ;;
    --with-hooks) with_hooks=1; shift ;;
    --dry-run) dry_run=1; shift ;;
    --profile) profile="${2:-full}"; shift 2 ;;
    --profile=*) profile="${1#--profile=}"; shift ;;
    -h|--help) usage; exit 0 ;;
    *)
      if [[ -n "$target_dir" ]]; then echo "Error: multiple target directories provided" >&2; usage; exit 1; fi
      target_dir="$1"; shift ;;
  esac
done

[[ -n "$target_dir" ]] || { echo "Error: target directory is required" >&2; usage; exit 1; }
case "$profile" in core|dev|growth|design|full) ;; *) echo "Error: unknown profile '$profile'" >&2; exit 2 ;; esac
if (( skills_only + sync_layer > 1 )); then echo "Error: use only one of --skills-only or --sync-layer" >&2; exit 2; fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
# Resolve and validate target directory
if [[ ! -d "$target_dir" ]]; then
  if (( dry_run )); then
    echo "[dry-run] Target does not exist yet: $target_dir"
    echo "[dry-run] Would install profile=$profile"
    exit 0
  fi
  echo "Error: target directory does not exist: $target_dir" >&2
  echo "Create it first: mkdir -p $target_dir" >&2
  exit 1
fi
target_dir="$(cd "$target_dir" && pwd)"

# Safety: refuse dangerous install targets
if [[ "$target_dir" == "/" ]]; then
  echo "Error: refusing to install into filesystem root (/)" >&2; exit 2
fi
if [[ "$target_dir" == "$(cd "$HOME" && pwd)" ]]; then
  echo "Error: refusing to install into HOME. Use a project subdirectory." >&2; exit 2
fi
if [[ "$target_dir" == "$repo_root" ]]; then
  echo "Error: target is the BosskuAI repo itself. Use a separate project directory." >&2; exit 2
fi
if [[ "$repo_root" == "$target_dir"/* ]]; then
  echo "Error: target ($target_dir) contains the BosskuAI repo. Refusing." >&2; exit 2
fi

copy_path() {
  local src="$repo_root/$1" dest="$target_dir/$1"
  [[ -e "$src" ]] || return 0
  mkdir -p "$(dirname "$dest")"
  rm -rf "$dest"
  cp -a "$src" "$dest"
}

apply_hooks_choice() {
  if (( with_hooks )); then
    cp -a "$target_dir/.claude/settings.hooks.example.json" "$target_dir/.claude/settings.json"
  else
    cat > "$target_dir/.claude/settings.json" <<'JSON'
{
  "bosskuai": {
    "hooks": "disabled-by-default",
    "note": "Run scripts/enable-hooks.sh or scripts/enable-hooks.ps1 to enable advisory Claude Code hooks with auto memory capture."
  }
}
JSON
  fi
}

copy_selected_skills() {
  local dest="$target_dir/ai-assistant/skills"
  rm -rf "$dest"; mkdir -p "$dest"
  local skills=("$@")
  for s in "${skills[@]}"; do
    if [[ -d "$repo_root/ai-assistant/skills/$s" ]]; then
      cp -a "$repo_root/ai-assistant/skills/$s" "$dest/$s"
    fi
  done
}

profile_skills() {
  local core=(bosskuai-workspace-assistant bosskuai-project-understanding bosskuai-permanent-memory-orchestration bosskuai-search-first bosskuai-human-output bosskuai-token-saver bosskuai-ratchet-loop bosskuai-continuous-learning bosskuai-context-limit-continuation)
  local dev=(bosskuai-engineering-delivery bosskuai-rigorous-code-review bosskuai-bug-finding bosskuai-software-architecture bosskuai-codebase-analysis bosskuai-code-revamp bosskuai-coding-best-practices bosskuai-devops-iac bosskuai-docker bosskuai-vps-docker-deployment bosskuai-github-workflow bosskuai-integration-testing bosskuai-laravel-development bosskuai-database-engineering bosskuai-redis-caching-queues)
  local growth=(bosskuai-market-analysis bosskuai-marketing-growth bosskuai-seo-geo bosskuai-sales-strategy bosskuai-launch-commercialization bosskuai-competitor-intelligence bosskuai-customer-discovery bosskuai-growth-experiment bosskuai-lead-intelligence bosskuai-content-calendar)
  local design=(bosskuai-ui-ux-design-to-code bosskuai-design-systems bosskuai-3d-web-development bosskuai-gsap-animation bosskuai-lenis-smooth-scroll)
  case "$profile" in
    core) printf '%s\n' "${core[@]}" ;;
    dev) printf '%s\n' "${core[@]}" "${dev[@]}" ;;
    growth) printf '%s\n' "${core[@]}" "${growth[@]}" ;;
    design) printf '%s\n' "${core[@]}" "${design[@]}" ;;
    full) find "$repo_root/ai-assistant/skills" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort ;;
  esac
}

entries=(AGENTS.md CLAUDE.md WORKSPACE-ONBOARDING.md skill-index.json agents mcp-configs .codex .claude .cursor .claude-plugin ai-assistant)

# Dry-run: show what would change and exit
if (( dry_run )); then
  echo "[dry-run] Target: $target_dir"
  echo "[dry-run] Profile: $profile"
  echo "[dry-run] Base entries:"; printf '  - %s\n' "${entries[@]}"
  echo "[dry-run] Skill profile:"; profile_skills | while read -r s; do printf '  - ai-assistant/skills/%s\n' "$s"; done
  echo "[dry-run] No changes made."
  exit 0
fi
if (( skills_only )); then entries=(ai-assistant); fi
if (( sync_layer )); then entries=(AGENTS.md CLAUDE.md WORKSPACE-ONBOARDING.md skill-index.json agents mcp-configs .codex .claude .cursor .claude-plugin ai-assistant); fi

conflicts=()
if (( ! sync_layer && ! skills_only )); then
  for e in "${entries[@]}"; do [[ -e "$target_dir/$e" ]] && conflicts+=("$e"); done
  if (( ${#conflicts[@]} > 0 && ! force )); then
    echo "Refusing to overwrite existing target entries:" >&2; printf '  - %s\n' "${conflicts[@]}" >&2
    echo "Re-run with --force to back up and replace those entries." >&2; exit 2
  fi
fi

memory_stash=""; had_memory=0
if (( preserve_memory )) && [[ -d "$target_dir/ai-assistant/memory" ]]; then
  memory_stash="$(mktemp -d "${TMPDIR:-/tmp}/bosskuai-memory-stash.XXXXXX")"
  cp -a "$target_dir/ai-assistant/memory/." "$memory_stash/"; had_memory=1
fi

if (( ${#conflicts[@]} > 0 && force )); then
  backup_dir="$target_dir/.bosskuai-backups/$(date '+%Y%m%d-%H%M%S')"; mkdir -p "$backup_dir"
  for e in "${conflicts[@]}"; do mkdir -p "$(dirname "$backup_dir/$e")"; mv "$target_dir/$e" "$backup_dir/$e"; done
fi

if (( skills_only )); then
  mkdir -p "$target_dir/ai-assistant"
  for sub in skills references scripts; do copy_path "ai-assistant/$sub"; done
else
  for e in AGENTS.md CLAUDE.md WORKSPACE-ONBOARDING.md skill-index.json agents mcp-configs .codex .claude .cursor .claude-plugin; do copy_path "$e"; done
  mkdir -p "$target_dir/ai-assistant"
  for sub in memory references scripts hooks; do copy_path "ai-assistant/$sub"; done
  mapfile -t selected < <(profile_skills)
  copy_selected_skills "${selected[@]}"
  apply_hooks_choice
fi

if (( had_memory )); then mkdir -p "$target_dir/ai-assistant/memory"; cp -a "$memory_stash/." "$target_dir/ai-assistant/memory/"; rm -rf "$memory_stash"; fi

echo "BosskuAI installed to: $target_dir"
echo "Profile: $profile"
(( with_hooks )) && echo "Hooks: enabled with auto memory capture" || echo "Hooks: disabled by default"
if (( skip_check == 0 )); then "$script_dir/check-workspace.sh" "$target_dir" --profile "$profile"; fi
