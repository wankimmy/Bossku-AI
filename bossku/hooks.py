from __future__ import annotations

import json
import shutil
import sys
from datetime import datetime, timezone
from pathlib import Path

from bossku.memory import sync_project

HOOK_MARKER = "sync-hook"
ALL_TOOLS = ("claude_code", "cursor", "codex", "opencode")


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


# --- install -----------------------------------------------------------

def install_claude_code_hook(home: Path) -> dict:
    base = home / ".claude"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "settings.json"
    data = _read_json(path)
    hooks = data.setdefault("hooks", {})
    stop = hooks.setdefault("Stop", [])
    if _has_marker(stop, HOOK_MARKER):
        return {"status": "already_installed", "path": str(path)}
    _backup(path)
    stop.append({"hooks": [{"type": "command", "command": f"{resolve_bossku_command()} sync-hook"}]})
    _write_json(path, data)
    return {"status": "installed", "path": str(path)}


def install_cursor_hook(home: Path) -> dict:
    base = home / ".cursor"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "hooks.json"
    data = _read_json(path)
    data.setdefault("version", 1)
    hooks = data.setdefault("hooks", {})
    stop = hooks.setdefault("stop", [])
    if _has_marker(stop, HOOK_MARKER):
        return {"status": "already_installed", "path": str(path)}
    _backup(path)
    stop.append({"command": f"{resolve_bossku_command()} sync-hook"})
    _write_json(path, data)
    return {"status": "installed", "path": str(path)}


def install_codex_hook(home: Path) -> dict:
    base = home / ".codex"
    if not base.is_dir():
        return {"status": "skipped_not_found", "path": str(base)}
    path = base / "hooks.json"
    data = _read_json(path)
    hooks = data.setdefault("hooks", {})
    stop = hooks.setdefault("Stop", [])
    if _has_marker(stop, HOOK_MARKER):
        return {"status": "already_installed", "path": str(path)}
    _backup(path)
    stop.append(
        {"hooks": [{"type": "command", "command": f"{resolve_bossku_command()} sync-hook", "timeout": 20}]}
    )
    _write_json(path, data)
    return {"status": "installed", "path": str(path)}


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
    stop = data.get("hooks", {}).get("Stop", [])
    remaining = _strip_marker(stop, HOOK_MARKER)
    if len(remaining) == len(stop):
        return {"status": "not_installed", "path": str(path)}
    _backup(path)
    data["hooks"]["Stop"] = remaining
    _write_json(path, data)
    return {"status": "removed", "path": str(path)}


def uninstall_cursor_hook(home: Path) -> dict:
    path = home / ".cursor" / "hooks.json"
    if not path.is_file():
        return {"status": "skipped_not_found", "path": str(path)}
    data = _read_json(path)
    stop = data.get("hooks", {}).get("stop", [])
    remaining = _strip_marker(stop, HOOK_MARKER)
    if len(remaining) == len(stop):
        return {"status": "not_installed", "path": str(path)}
    _backup(path)
    data["hooks"]["stop"] = remaining
    _write_json(path, data)
    return {"status": "removed", "path": str(path)}


def uninstall_codex_hook(home: Path) -> dict:
    path = home / ".codex" / "hooks.json"
    if not path.is_file():
        return {"status": "skipped_not_found", "path": str(path)}
    data = _read_json(path)
    stop = data.get("hooks", {}).get("Stop", [])
    remaining = _strip_marker(stop, HOOK_MARKER)
    if len(remaining) == len(stop):
        return {"status": "not_installed", "path": str(path)}
    _backup(path)
    data["hooks"]["Stop"] = remaining
    _write_json(path, data)
    return {"status": "removed", "path": str(path)}


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
    checks = {
        "claude_code": (h / ".claude" / "settings.json", ("hooks", "Stop")),
        "cursor": (h / ".cursor" / "hooks.json", ("hooks", "stop")),
        "codex": (h / ".codex" / "hooks.json", ("hooks", "Stop")),
    }
    for tool, (path, keys) in checks.items():
        if not path.is_file():
            status[tool] = False
            continue
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError):
            status[tool] = False
            continue
        node = data
        for key in keys:
            node = node.get(key, []) if isinstance(node, dict) else []
        status[tool] = _has_marker(node if isinstance(node, list) else [], HOOK_MARKER)
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
