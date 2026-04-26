#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="${1:-$script_dir/../..}"
repo_root="$(cd "$repo_root" && pwd)"

memory_dir="$repo_root/ai-assistant/memory"
refs_dir="$repo_root/ai-assistant/references"
skills_dir="$repo_root/ai-assistant/skills"
project_understanding="$memory_dir/project-understanding.md"
learning_log="$memory_dir/learning-log.md"
bug_patterns="$memory_dir/bug-patterns.md"
market_notes="$memory_dir/market-notes.md"
active_continuation="$memory_dir/active-continuation.md"

warnings=()
advisories=()
passes=()

add_warning() {
  warnings+=("$1")
}

add_pass() {
  passes+=("$1")
}

add_advisory() {
  advisories+=("$1")
}

count_files() {
  find "$1" -maxdepth 1 -name '*.md' | wc -l | tr -d ' '
}

actual_skills="$(find "$skills_dir" -name 'SKILL.md' -type f | wc -l | tr -d ' ')"
actual_checklists="$(count_files "$refs_dir/checklists")"
actual_playbooks="$(count_files "$refs_dir/playbooks")"
actual_pitfalls="$(count_files "$refs_dir/pitfalls")"

if [[ -f "$project_understanding" ]]; then
  surface_line="$(grep -n 'Current expert surface size:' "$project_understanding" || true)"
  if [[ -n "$surface_line" ]]; then
    declared_skills="$(echo "$surface_line" | sed -E 's/.*: ([0-9]+) skills, ([0-9]+) checklists, and ([0-9]+) playbooks.*/\1/')"
    declared_checklists="$(echo "$surface_line" | sed -E 's/.*: ([0-9]+) skills, ([0-9]+) checklists, and ([0-9]+) playbooks.*/\2/')"
    declared_playbooks="$(echo "$surface_line" | sed -E 's/.*: ([0-9]+) skills, ([0-9]+) checklists, and ([0-9]+) playbooks.*/\3/')"
    if [[ "$declared_skills" != "$actual_skills" || "$declared_checklists" != "$actual_checklists" || "$declared_playbooks" != "$actual_playbooks" ]]; then
      add_warning "Stale expert surface counts in ai-assistant/memory/project-understanding.md (declared: ${declared_skills}/${declared_checklists}/${declared_playbooks}; actual: ${actual_skills}/${actual_checklists}/${actual_playbooks})."
    else
      add_pass "Project-understanding expert surface counts match the repo."
    fi
  else
    add_warning "ai-assistant/memory/project-understanding.md does not record current expert surface size."
  fi
fi

if grep -q '^## Legacy entries' "$learning_log"; then
  add_warning "learning-log.md still contains legacy pre-schema entries; distill or archive them when practical."
fi

if grep -q '^### ' "$learning_log"; then
  add_pass "learning-log.md contains structured entries."
else
  add_warning "learning-log.md has no structured entries using the current schema."
fi

if grep -q '_(None yet' "$bug_patterns"; then
  add_advisory "bug-patterns.md is still empty; this is fine for a starter repo. Add the first recurring or high-severity defect pattern when one appears."
else
  add_pass "bug-patterns.md has at least one recorded pattern."
fi

if grep -q '_(None yet' "$market_notes"; then
  add_advisory "market-notes.md is still empty; this is fine for a starter repo. Add a durable GTM or competitor lesson when one is verified."
else
  add_pass "market-notes.md has at least one recorded market lesson."
fi

if grep -Eq '^- \*\*(Date|Tool|Reason|Phase|Primary|Fallback):\*\* [^[:space:](]' "$active_continuation"; then
  add_warning "active-continuation.md appears populated; clear it if the continuation has already been consumed."
else
  add_pass "active-continuation.md looks like a clean template."
fi

echo "BosskuAI Learning Doctor"
echo "Repo: $repo_root"
echo "Actual inventory: $actual_skills skills, $actual_checklists checklists, $actual_playbooks playbooks, $actual_pitfalls pitfall files"
echo

if [[ "${#passes[@]}" -gt 0 ]]; then
  echo "Passing checks"
  for item in "${passes[@]}"; do
    echo "- $item"
  done
  echo
fi

if [[ "${#warnings[@]}" -gt 0 ]]; then
  echo "Warnings"
  for item in "${warnings[@]}"; do
    echo "- $item"
  done
  echo
  echo "Status: PARTIAL"
  exit 0
fi

if [[ "${#advisories[@]}" -gt 0 ]]; then
  echo "Starter advisories"
  for item in "${advisories[@]}"; do
    echo "- $item"
  done
  echo
  echo "Status: PASS"
  exit 0
fi

echo "Status: PASS"
