#!/usr/bin/env bash

# Advisory hook: fires on PreToolUse when any mcp__ tool is used.
# Checks if the required env var is set for that MCP server.
# Always exits 0 — never blocks.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input

# First argument is the tool name e.g. mcp__exa__search
tool_name="${1:-}"

if [[ -z "$tool_name" ]]; then
  write_hook_output
  exit 0
fi

# Extract server name (second segment: mcp__<server>__<action>)
server_name="$(echo "$tool_name" | cut -d'__' -f2)"

# Map server to required env var
required_var=""
case "$server_name" in
  exa)         required_var="EXA_API_KEY" ;;
  firecrawl)   required_var="FIRECRAWL_API_KEY" ;;
  github)      required_var="GITHUB_PAT" ;;
  playwright)  required_var="" ;;  # no key needed
  context7)    required_var="" ;;  # no key needed
  pencil)      required_var="" ;;  # no key needed
  *)           required_var="" ;;
esac

# Warn if required var is missing
if [[ -n "$required_var" ]] && [[ -z "${!required_var:-}" ]]; then
  {
    echo "── BosskuAI MCP Health Warning ─────────────────────────"
    echo "  ⚠  MCP server \"$server_name\" requires env var: $required_var"
    echo "     Setup: export $required_var=\"your-key\""
    echo "     Docs: bosskuAI/README.md and bosskuAI/mcp-configs/README.md"
    echo "────────────────────────────────────────────────────────"
  } >&2
fi

write_hook_output
exit 0
