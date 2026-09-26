from __future__ import annotations

import json
import os
import re
import shutil
import stat
import sys
from datetime import datetime, timezone
from pathlib import Path

from bossku.memory import sync_project
from bossku.paths import user_config_dir

HOOK_MARKER = "sync-hook"
ALL_TOOLS = ("claude_code", "cursor", "codex", "opencode")

# Denser defaults (additive). Marker-checked per event so upgrades fill gaps.
CURSOR_EVENTS = ("stop", "sessionEnd", "afterAgentResponse")
CLAUDE_EVENTS = ("Stop", "SessionEnd")
CODEX_EVENTS = ("Stop", "SessionEnd")


def resolve_bossku_command() -> str:
    exe = shutil.which("bossku")
    if exe:
        return exe
    return f"{sys.executable} -m bossku"


def _backup(path: Path) -> None:
    if not path.is_file():
        return
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    backup = path.with_name(f"{path.name}.bak-{stamp}")
    backup.write_text(path.read_text(encoding="utf-8"), encoding="utf-8")


def _read_json(path: Path) -> dict:
    if not path.is_file():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def _write_json(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2), encoding="utf-8")


def _has_marker(entries: list, marker: str) -> bool:
    return any(marker in json.dumps(entry) for entry in entries)


def _strip_marker(entries: list, marker: str) -> list:
    return [entry for entry in entries if marker not in json.dumps(entry)]


def _ensure_event(hooks: dict, event: str, entry: dict) -> bool:
    """Add marked entry for event if missing. Returns True when something was added."""
    bucket = hooks.get(event)
    if not isinstance(bucket, list):
        bucket = []
        hooks[event] = bucket
    if _has_marker(bucket, HOOK_MARKER):
        return False
    bucket.append(entry)
    return True


def _strip_events(hooks: dict, events: tuple[str, ...]) -> bool:
    changed = False
    for event in events:
        entries = hooks.get(event)
        if not isinstance(entries, list):
            continue
        remaining = _strip_marker(entries, HOOK_MARKER)
        if len(remaining) == len(entries):
            continue
        changed = True
        if remaining:
            hooks[event] = remaining
        else:
            del hooks[event]
    return changed


def _events_complete(hooks: dict, events: tuple[str, ...]) -> bool:
    return all(
        isinstance(hooks.get(ev), list) and _has_marker(hooks[ev], HOOK_MARKER) for ev in events
    )


def _sync_cmd() -> str:
    return f"{resolve_bossku_command()} sync-hook"


def _claude_sync_cmd() -> str:
    """Quoted forward-slash path: Claude Code runs hooks through Git Bash on Windows,
    where an unquoted `C:\\Users\\...\\bossku.EXE` loses its backslashes."""
    exe = shutil.which("bossku")
    if exe:
        return f'"{Path(exe).as_posix()}" sync-hook'
    return f'"{Path(sys.executable).as_posix()}" -m bossku sync-hook'


def _has_command(entries: list, command: str) -> bool:
    return any(
        isinstance(hook, dict) and hook.get("command") == command
        for entry in entries
        if isinstance(entry, dict)
        for hook in entry.get("hooks", [])
    )


# --- Codex continue-safe wrappers ----------------------------------------

_CODEX_SH = """#!/usr/bin/env bash
# Managed by BosskuAI (`bossku hooks install`). Codex Stop/SessionEnd wrapper.
# Curated one-way Obsidian export, then continue JSON required by Codex Stop.
set -u
if [ -t 0 ]; then
  INPUT=""
else
  INPUT="$(cat || true)"
fi
run_sync() {
  if command -v bossku >/dev/null 2>&1; then
    if [ -n "$INPUT" ]; then printf '%s' "$INPUT" | bossku sync-hook; else bossku sync-hook </dev/null; fi
  else
    if [ -n "$INPUT" ]; then printf '%s' "$INPUT" | python3 -m bossku sync-hook; else python3 -m bossku sync-hook </dev/null; fi
  fi
}
run_sync >/dev/null 2>&1 || true
printf '%s\n' '{"continue": true}'
"""

_CODEX_PS1 = """# Managed by BosskuAI (`bossku hooks install`). Codex Stop/SessionEnd wrapper.
# Curated one-way Obsidian export, then continue JSON required by Codex Stop.
$ErrorActionPreference = 'Continue'
$inputJson = ''
try { $inputJson = [Console]::In.ReadToEnd() } catch {}
function Invoke-BosskuSyncHook {
  if (Get-Command bossku -ErrorAction SilentlyContinue) {
    if ($inputJson) { $inputJson | & bossku sync-hook | Out-Null }
    else { & bossku sync-hook | Out-Null }
    return
  }
  $py = Get-Command python -ErrorAction SilentlyContinue
  if (-not $py) { $py = Get-Command python3 -ErrorAction SilentlyContinue }
  if ($py) {
    if ($inputJson) { $inputJson | & $py.Source -m bossku sync-hook | Out-Null }
    else { & $py.Source -m bossku sync-hook | Out-Null }
  }
}
try { Invoke-BosskuSyncHook } catch {}
Write-Output '{"continue": true}'
"""


def _scripts_dir() -> Path | None:
    candidate = Path(__file__).resolve().parent.parent / "scripts"
    return candidate if candidate.is_dir() else None


def ensure_codex_wrapper(home: Path, *, windows: bool | None = None) -> Path:
    """Materialize ~/.bosskuai/codex-sync-hook.(sh|ps1) from scripts/ templates when present."""
    cfg = user_config_dir(home)
    cfg.mkdir(parents=True, exist_ok=True)
    use_win = os.name == "nt" if windows is None else windows
    scripts = _scripts_dir()
    if use_win:
        dest = cfg / "codex-sync-hook.ps1"
        shipped = scripts / "codex-sync-hook.ps1" if scripts else None
        body = shipped.read_text(encoding="utf-8") if shipped and shipped.is_file() else _CODEX_PS1
    else:
        dest = cfg / "codex-sync-hook.sh"
        shipped = scripts / "codex-sync-hook.sh" if scripts else None
        body = shipped.read_text(encoding="utf-8") if shipped and shipped.is_file() else _CODEX_SH
    dest.write_text(body, encoding="utf-8")
    if not use_win:
        dest.chmod(dest.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
    return dest


def codex_wrapper_command(wrapper: Path) -> str:
    if wrapper.suffix.lower() == ".ps1":
        quoted = str(wrapper).replace("'", "''")
        return f"powershell -NoProfile -ExecutionPolicy Bypass -File '{quoted}'"
    return str(wrapper)


def enable_codex_hooks_feature(home: Path) -> dict:
    """Set [features] hooks = true in ~/.codex/config.toml without wiping other keys."""
    base = home / ".codex"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base / "config.toml"), "changed": False}
    path = base / "config.toml"
    if not path.is_file():
        path.write_text("[features]\nhooks = true\n", encoding="utf-8")
        return {"status": "installed", "path": str(path), "changed": True}

    original = path.read_text(encoding="utf-8")
    updated, changed = _enable_hooks_in_toml(original)
    if not changed:
        return {"status": "already_installed", "path": str(path), "changed": False}
    _backup(path)
    path.write_text(updated, encoding="utf-8")
    return {"status": "installed", "path": str(path), "changed": True}


def _enable_hooks_in_toml(text: str) -> tuple[str, bool]:
    if re.search(r"(?im)^hooks\s*=\s*true\s*$", text):
        return text, False
    if re.search(r"(?im)^hooks\s*=\s*false\s*$", text):
        new = re.sub(r"(?im)^hooks\s*=\s*false\s*$", "hooks = true", text, count=1)
        return new, new != text
    features = re.search(r"(?im)^\[features\]\s*$", text)
    if features:
        return text[: features.end()] + "\nhooks = true" + text[features.end() :], True
    suffix = "" if text.endswith("\n") or text == "" else "\n"
    return text + suffix + "\n[features]\nhooks = true\n", True


# --- install -----------------------------------------------------------

def install_claude_code_hook(home: Path) -> dict:
    base = home / ".claude"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "settings.json"
    data = _read_json(path)
    hooks = data.setdefault("hooks", {})
    if not isinstance(hooks, dict):
        hooks = {}
        data["hooks"] = hooks
    command = _claude_sync_cmd()
    entry = {"hooks": [{"type": "command", "command": command}]}
    for ev in CLAUDE_EVENTS:
        bucket = hooks.get(ev)
        # Our own entry from an older install may hold a command this shell cannot run.
        if isinstance(bucket, list) and _has_marker(bucket, HOOK_MARKER) and not _has_command(bucket, command):
            hooks[ev] = _strip_marker(bucket, HOOK_MARKER)
    added = [ev for ev in CLAUDE_EVENTS if _ensure_event(hooks, ev, json.loads(json.dumps(entry)))]
    if not added:
        return {"status": "already_installed", "path": str(path), "events": list(CLAUDE_EVENTS)}
    _backup(path)
    _write_json(path, data)
    return {"status": "installed", "path": str(path), "events": list(CLAUDE_EVENTS), "added": added}


def install_cursor_hook(home: Path) -> dict:
    base = home / ".cursor"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "hooks.json"
    data = _read_json(path)
    data.setdefault("version", 1)
    hooks = data.setdefault("hooks", {})
    if not isinstance(hooks, dict):
        hooks = {}
        data["hooks"] = hooks
    entry = {"command": _sync_cmd()}
    added = [ev for ev in CURSOR_EVENTS if _ensure_event(hooks, ev, dict(entry))]
    if not added:
        return {"status": "already_installed", "path": str(path), "events": list(CURSOR_EVENTS)}
    _backup(path)
    _write_json(path, data)
    return {"status": "installed", "path": str(path), "events": list(CURSOR_EVENTS), "added": added}


def install_codex_hook(home: Path) -> dict:
    base = home / ".codex"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "hooks.json"
    data = _read_json(path)
    hooks = data.setdefault("hooks", {})
    if not isinstance(hooks, dict):
        hooks = {}
        data["hooks"] = hooks

    wrapper = ensure_codex_wrapper(home)
    command = codex_wrapper_command(wrapper)
    entry = {"hooks": [{"type": "command", "command": command, "timeout": 20}]}
    added = [ev for ev in CODEX_EVENTS if _ensure_event(hooks, ev, json.loads(json.dumps(entry)))]
    feature = enable_codex_hooks_feature(home)

    if not added and not feature.get("changed"):
        return {
            "status": "already_installed",
            "path": str(path),
            "events": list(CODEX_EVENTS),
            "wrapper": str(wrapper),
            "features": feature,
        }
    if added:
        _backup(path)
        _write_json(path, data)
    return {
        "status": "installed",
        "path": str(path),
        "events": list(CODEX_EVENTS),
        "added": added,
        "wrapper": str(wrapper),
        "features": feature,
    }


_OPENCODE_PLUGIN_TEMPLATE = """// Managed by BosskuAI (`bossku hooks install`). Safe to delete; re-run to restore.
// Pushes .bossku/memory to the configured Obsidian vault when an OpenCode session goes idle.
import {{ spawn }} from "node:child_process";

const BOSSKU_CMD = {cmd!r};

export const BosskuSyncPlugin = async (input) => {{
  return {{
    event: (arg) => {{
      const ev = arg && arg.event;
      if (!ev || ev.type !== "session.idle") return;
      const cwd = input.directory || (input.project && input.project.worktree) || process.cwd();
      try {{
        const parts = BOSSKU_CMD.split(" ");
        const bin = parts.shift();
        const child = spawn(bin, [...parts, "sync-hook", "--project", cwd], {{
          detached: true,
          stdio: "ignore",
        }});
        child.unref();
      }} catch (e) {{
        console.warn(`[bossku-sync] failed to spawn sync-hook: ${{e instanceof Error ? e.message : String(e)}}`);
      }}
    }},
  }};
}};

export default BosskuSyncPlugin;
"""


def install_opencode_plugin(home: Path) -> dict:
    base = home / ".config" / "opencode"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "plugins" / "bossku-sync.js"
    was_present = path.is_file()
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(_OPENCODE_PLUGIN_TEMPLATE.format(cmd=resolve_bossku_command()), encoding="utf-8")
    return {"status": "already_installed" if was_present else "installed", "path": str(path)}


_INSTALLERS = {
    "claude_code": install_claude_code_hook,
    "cursor": install_cursor_hook,
    "codex": install_codex_hook,
    "opencode": install_opencode_plugin,
}


def install_hooks(*, home: Path | None = None, tools: tuple[str, ...] | None = None) -> dict:
    h = home if home is not None else Path.home()
    result: dict = {}
    for tool in tools or ALL_TOOLS:
        installer = _INSTALLERS.get(tool)
        if installer is None:
            result[tool] = {"status": "error", "message": f"unknown tool {tool!r}"}
            continue
        try:
            result[tool] = installer(h)
        except Exception as exc:  # noqa: BLE001 - one tool's failure must not abort the rest
            result[tool] = {"status": "error", "message": str(exc)}
    return result


# --- uninstall -----------------------------------------------------------

def uninstall_claude_code_hook(home: Path) -> dict:
    path = home / ".claude" / "settings.json"
    if not path.is_file():
        return {"status": "skipped_not_found", "path": str(path)}
    data = _read_json(path)
    hooks = data.get("hooks", {})
    if not isinstance(hooks, dict) or not _strip_events(hooks, CLAUDE_EVENTS):
        return {"status": "not_installed", "path": str(path)}
    _backup(path)
    data["hooks"] = hooks
    _write_json(path, data)
    return {"status": "removed", "path": str(path)}


def uninstall_cursor_hook(home: Path) -> dict:
    path = home / ".cursor" / "hooks.json"
    if not path.is_file():
        return {"status": "skipped_not_found", "path": str(path)}
    data = _read_json(path)
    hooks = data.get("hooks", {})
    if not isinstance(hooks, dict) or not _strip_events(hooks, CURSOR_EVENTS):
        return {"status": "not_installed", "path": str(path)}
    _backup(path)
    data["hooks"] = hooks
    _write_json(path, data)
    return {"status": "removed", "path": str(path)}


def uninstall_codex_hook(home: Path) -> dict:
    path = home / ".codex" / "hooks.json"
    changed = False
    if path.is_file():
        data = _read_json(path)
        hooks = data.get("hooks", {})
        if isinstance(hooks, dict) and _strip_events(hooks, CODEX_EVENTS):
            _backup(path)
            data["hooks"] = hooks
            _write_json(path, data)
            changed = True

    removed_wrappers: list[str] = []
    cfg = user_config_dir(home)
    for name in ("codex-sync-hook.sh", "codex-sync-hook.ps1"):
        wrapper = cfg / name
        if wrapper.is_file():
            wrapper.unlink()
            removed_wrappers.append(str(wrapper))
            changed = True

    if not changed:
        return {"status": "not_installed" if path.is_file() else "skipped_not_found", "path": str(path)}
    result: dict = {"status": "removed", "path": str(path)}
    if removed_wrappers:
        result["wrappers_removed"] = removed_wrappers
    return result


def uninstall_opencode_plugin(home: Path) -> dict:
    path = home / ".config" / "opencode" / "plugins" / "bossku-sync.js"
    if not path.is_file():
        return {"status": "skipped_not_found", "path": str(path)}
    path.unlink()
    return {"status": "removed", "path": str(path)}


_UNINSTALLERS = {
    "claude_code": uninstall_claude_code_hook,
    "cursor": uninstall_cursor_hook,
    "codex": uninstall_codex_hook,
    "opencode": uninstall_opencode_plugin,
}


def uninstall_hooks(*, home: Path | None = None, tools: tuple[str, ...] | None = None) -> dict:
    h = home if home is not None else Path.home()
    result: dict = {}
    for tool in tools or ALL_TOOLS:
        uninstaller = _UNINSTALLERS.get(tool)
        if uninstaller is None:
            result[tool] = {"status": "error", "message": f"unknown tool {tool!r}"}
            continue
        try:
            result[tool] = uninstaller(h)
        except Exception as exc:  # noqa: BLE001
            result[tool] = {"status": "error", "message": str(exc)}
    return result


# --- status (read-only, used by `bossku doctor`) --------------------------

def hooks_status(home: Path | None = None) -> dict:
    h = home if home is not None else Path.home()
    status: dict[str, bool] = {}

    claude_path = h / ".claude" / "settings.json"
    claude_hooks = _read_json(claude_path).get("hooks", {}) if claude_path.is_file() else {}
    status["claude_code"] = isinstance(claude_hooks, dict) and _events_complete(claude_hooks, CLAUDE_EVENTS)

    cursor_path = h / ".cursor" / "hooks.json"
    cursor_hooks = _read_json(cursor_path).get("hooks", {}) if cursor_path.is_file() else {}
    status["cursor"] = isinstance(cursor_hooks, dict) and _events_complete(cursor_hooks, CURSOR_EVENTS)

    codex_path = h / ".codex" / "hooks.json"
    codex_hooks = _read_json(codex_path).get("hooks", {}) if codex_path.is_file() else {}
    status["codex"] = isinstance(codex_hooks, dict) and _events_complete(codex_hooks, CODEX_EVENTS)

    status["opencode"] = (h / ".config" / "opencode" / "plugins" / "bossku-sync.js").is_file()
    return status


# --- the hook entrypoint itself (`bossku sync-hook`) -----------------------

def run_sync_hook(*, project: Path | None = None, home: Path | None = None) -> dict:
    cwd = project
    if cwd is None and not sys.stdin.isatty():
        try:
            raw = sys.stdin.read()
            payload = json.loads(raw) if raw.strip() else {}
            cwd_str = payload.get("cwd") if isinstance(payload, dict) else None
            if cwd_str:
                cwd = Path(cwd_str)
        except (json.JSONDecodeError, OSError):
            cwd = None
    if cwd is None:
        cwd = Path.cwd()
    try:
        return sync_project(cwd, home=home)
    except Exception as exc:  # noqa: BLE001 - a safety-net hook must never break the caller's session
        return {"status": "error", "reason": str(exc)}
