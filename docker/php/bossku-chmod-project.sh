#!/usr/bin/env sh
# Root-only helper: make a single bind-mounted project tree writable (www-data invokes via sudo -n).
set -e
ROOT="${1:-}"
if [ -z "$ROOT" ] || [ ! -d "$ROOT" ]; then
  echo "bossku-chmod-project: missing or invalid directory" >&2
  exit 1
fi
find "$ROOT" -maxdepth 12 \
  \( -path '*/node_modules' -o -path '*/node_modules/*' \
  -o -path '*/vendor' -o -path '*/vendor/*' \
  -o -path '*/.git' -o -path '*/.git/*' \) -prune \
  -o -exec chmod a+rwX {} + 2>/dev/null || true
