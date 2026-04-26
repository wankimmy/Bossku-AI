#!/usr/bin/env bash
set -euo pipefail

target_dir="${1:-.}"
target_dir="$(cd "$target_dir" && pwd)"
python3 -S - "$target_dir" <<'PY'
from __future__ import annotations
import json, sys
from pathlib import Path
root = Path(sys.argv[1])
idx_path = root / 'skill-index.json'
skills_dir = root / 'ai-assistant/skills'
mem_dir = root / 'ai-assistant/memory'
errors=[]
if not idx_path.exists(): errors.append(f'missing {idx_path}')
if not skills_dir.exists(): errors.append(f'missing {skills_dir}')
if errors:
    print('BosskuAI skill-index validation')
    print('Status: FAIL')
    print('\n'.join(f'  - {e}' for e in errors))
    raise SystemExit(1)
idx=json.loads(idx_path.read_text(encoding='utf-8'))
ids=[s['id'] for s in idx.get('skills', [])]
folders=sorted(p.name for p in skills_dir.iterdir() if p.is_dir())
for sid in ids:
    if not (skills_dir/sid/'SKILL.md').exists(): errors.append(f'indexed skill missing SKILL.md: {sid}')
for f in folders:
    if f not in ids: errors.append(f'orphan skill folder not indexed: {f}')
for mf in ['agent-profile.md','project-understanding.md','plan-log.md','learning-log.md','bug-patterns.md','market-notes.md']:
    if not (mem_dir/mf).exists(): errors.append(f'missing memory file: {mf}')
for s in idx.get('skills', []):
    if s.get('status') == 'deprecated_alias':
        repl=s.get('replaced_by')
        if not repl: errors.append(f'deprecated alias missing replaced_by: {s.get("id")}')
        elif not (skills_dir/repl/'SKILL.md').exists(): errors.append(f'deprecated alias replacement missing: {s.get("id")} -> {repl}')
for sid in idx.get('routing', {}).get('core_skill_ids', []):
    if not (skills_dir/sid/'SKILL.md').exists(): errors.append(f'core skill missing: {sid}')
print('BosskuAI skill-index validation')
print(f'Target: {root}')
print(f'Version: {idx.get("version", "unknown")}')
print(f'Indexed skills: {len(ids)}')
print(f'Skill folders: {len(folders)}')
if errors:
    print('Status: FAIL')
    for e in errors: print(f'  - {e}')
    raise SystemExit(1)
print('Status: PASS')
PY
