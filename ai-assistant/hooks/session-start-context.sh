#!/usr/bin/env bash

# SessionStart hook: prints the compact BosskuAI contract to STDOUT so it is
# injected into model context in ANY repo the plugin is enabled for.
# Keep this under ~150 tokens — it is paid once per session, everywhere.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$script_dir/common.sh"

read_hook_input

project="${CLAUDE_PROJECT_DIR:-$PWD}"
home="$(resolve_bossku_home)"

# A plugin cache copy is a disposable snapshot — never treat it as the memory home.
if [[ ! -d "$home/ai-assistant/memory" || "$home" == *"/.claude/plugins/"* ]]; then
  memory_line="Shared memory unavailable here — set BOSSKU_HOME to your Bossku-AI checkout to enable it."
elif [[ -f "$home/ai-assistant/scripts/auto_memory.py" ]]; then
  memory_line="Memory home: ${home}/ai-assistant/memory — query first: python3 ${home}/ai-assistant/scripts/auto_memory.py query \"<task>\" --limit 5; write durable outcomes back after meaningful work."
else
  memory_line="Memory home: ${home}/ai-assistant/memory — read/write memory files directly (auto_memory.py not installed here; refresh with Bossku-AI scripts/install.sh --sync-layer)."
fi

cat <<EOF
[BosskuAI] contract active (project: ${project}).
- Start replies with the indicator: [BOSSKUAI] / Skill / Agent / Model Role / Memory Used.
- Non-trivial work: load ONE primary bossku-ai:* skill via the Skill tool, then plan -> execute -> audit, looping until the pass signal is met.
- Ponytail (lazy senior dev) ON by default: simplest thing that works — YAGNI, stdlib/native before deps, one line before fifty, fewest files, deletion over addition. Never lazy about validation, security, accessibility, data-loss, or the one runnable check. Off: "stop ponytail". Skill: bosskuai-ponytail.
- Anti-slop ON by default: no generic placeholders (Jane Doe/Acme), no filler verbs (Elevate/Seamless/Unleash), no fake-perfect numbers, no em-dash decoration. Frontend/UI work -> load bosskuai-taste first (Design Read, reach past LLM defaults, real design systems + real images, pre-flight check).
- ${memory_line}
- Prefer small diffs and targeted reads. Escalate to review when touching auth, payments, secrets, or migrations.
EOF

exit 0
