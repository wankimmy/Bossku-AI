from __future__ import annotations

import json
import re
from pathlib import Path

from bossku.index import index_is_stale, index_path
from bossku.paths import repo_root
from bossku.skills import (
    is_managed_skill_name,
    list_skill_ids,
    load_provenance,
    load_vendored_ids,
    pack_stocktake,
    skills_dir,
    validate_skills,
)


REQUIRED_FILES = (
    "AGENTS.md",
    "CLAUDE.md",
    ".omp/AGENTS.md",
    ".omp/config.yml",
    "README.md",
    "pyproject.toml",
    "skills/aliases.json",
    "skills/vendored.json",
)

REQUIRED_AGENTS = (
    "orchestrator.md",
    "planner.md",
    "executor.md",
    "auditor.md",
    "final-reviewer.md",
)

CLAUDE_AGENTS_IMPORT = "@AGENTS.md"
OMP_AGENTS_IMPORT = "@../AGENTS.md"
PLUGIN_NAME = "bossku-ai"
MARKETPLACE_NAME = "bosskuai-marketplace"
CODEX_MARKETPLACE_NAME = "bosskuai"


def package_version(root: Path) -> str:
    text = (root / "pyproject.toml").read_text(encoding="utf-8")
    match = re.search(r'^version\s*=\s*"([^"]+)"', text, re.MULTILINE)
    if not match:
        raise ValueError("pyproject.toml missing version")
    return match.group(1)


def _load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def _path_exists(root: Path, rel: str) -> bool:
    return (root / rel).exists()


def _validate_manifest_version(
    errors: list[str],
    label: str,
    manifest_version: str | None,
    expected: str,
) -> None:
    if manifest_version is None:
        errors.append(f"{label}: missing version")
        return
    if manifest_version != expected:
        errors.append(
            f"{label}: version {manifest_version!r} does not match pyproject.toml {expected!r}"
        )


def _validate_component_paths(
    errors: list[str],
    root: Path,
    label: str,
    manifest: dict,
    fields: tuple[str, ...],
) -> None:
    for field in fields:
        value = manifest.get(field)
        if value is None:
            continue
        paths = value if isinstance(value, list) else [value]
        for rel in paths:
            if not _path_exists(root, rel):
                errors.append(f"{label}: missing {field} path: {rel}")


def validate_plugin_manifests(root: Path) -> list[str]:
    errors: list[str] = []
    expected_version = package_version(root)

    claude_plugin = root / ".claude-plugin" / "plugin.json"
    claude_marketplace = root / ".claude-plugin" / "marketplace.json"
    cursor_plugin = root / ".cursor-plugin" / "plugin.json"
    cursor_marketplace = root / ".cursor-plugin" / "marketplace.json"
    codex_plugin = root / ".codex-plugin" / "plugin.json"
    codex_marketplace = root / ".agents" / "plugins" / "marketplace.json"
    opencode_config = root / ".opencode" / "opencode.jsonc"

    required = {
        "claude plugin manifest": claude_plugin,
        "claude marketplace manifest": claude_marketplace,
        "cursor plugin manifest": cursor_plugin,
        "cursor marketplace manifest": cursor_marketplace,
        "codex plugin manifest": codex_plugin,
        "codex marketplace manifest": codex_marketplace,
        "opencode config": opencode_config,
    }
    for label, path in required.items():
        if not path.is_file():
            errors.append(f"missing {label}: {path.relative_to(root).as_posix()}")

    if errors:
        return errors

    try:
        claude_plugin_data = _load_json(claude_plugin)
        claude_marketplace_data = _load_json(claude_marketplace)
        cursor_plugin_data = _load_json(cursor_plugin)
        cursor_marketplace_data = _load_json(cursor_marketplace)
        codex_plugin_data = _load_json(codex_plugin)
        codex_marketplace_data = _load_json(codex_marketplace)
        opencode_data = _load_json(opencode_config)
    except json.JSONDecodeError as exc:
        errors.append(f"invalid plugin JSON: {exc}")
        return errors

    if claude_plugin_data.get("name") != PLUGIN_NAME:
        errors.append("claude plugin manifest: name must be bossku-ai")
    if claude_marketplace_data.get("name") != MARKETPLACE_NAME:
        errors.append("claude marketplace manifest: name must be bosskuai-marketplace")
    if cursor_plugin_data.get("name") != PLUGIN_NAME:
        errors.append("cursor plugin manifest: name must be bossku-ai")
    if codex_plugin_data.get("name") != PLUGIN_NAME:
        errors.append("codex plugin manifest: name must be bossku-ai")
    if codex_marketplace_data.get("name") != CODEX_MARKETPLACE_NAME:
        errors.append("codex marketplace manifest: name must be bosskuai")

    _validate_manifest_version(
        errors,
        "claude plugin manifest",
        claude_plugin_data.get("version"),
        expected_version,
    )
    _validate_manifest_version(
        errors,
        "cursor plugin manifest",
        cursor_plugin_data.get("version"),
        expected_version,
    )
    _validate_manifest_version(
        errors,
        "codex plugin manifest",
        codex_plugin_data.get("version"),
        expected_version,
    )

    _validate_component_paths(
        errors,
        root,
        "claude plugin manifest",
        claude_plugin_data,
        ("skills", "agents"),
    )
    _validate_component_paths(
        errors,
        root,
        "cursor plugin manifest",
        cursor_plugin_data,
        ("skills", "agents", "rules"),
    )
    _validate_component_paths(
        errors,
        root,
        "codex plugin manifest",
        codex_plugin_data,
        ("skills",),
    )

    claude_plugins = claude_marketplace_data.get("plugins", [])
    if not any(
        entry.get("name") == PLUGIN_NAME and entry.get("source") == "./"
        for entry in claude_plugins
    ):
        errors.append(
            "claude marketplace manifest: must list bossku-ai with source ./"
        )

    cursor_plugins = cursor_marketplace_data.get("plugins", [])
    if not any(
        entry.get("name") == PLUGIN_NAME and entry.get("source") == "./"
        for entry in cursor_plugins
    ):
        errors.append(
            "cursor marketplace manifest: must list bossku-ai with source ./"
        )

    codex_plugins = codex_marketplace_data.get("plugins", [])
    codex_entry = next(
        (entry for entry in codex_plugins if entry.get("name") == PLUGIN_NAME),
        None,
    )
    if codex_entry is None:
        errors.append("codex marketplace manifest: missing bossku-ai plugin entry")
    else:
        source = codex_entry.get("source", {})
        rel_path = source.get("path")
        if source.get("source") != "local" or rel_path != "./../..":
            errors.append(
                "codex marketplace manifest: bossku-ai source must be local ./../.."
            )
        else:
            resolved = (codex_marketplace.parent / rel_path).resolve()
            if resolved != root.resolve():
                errors.append(
                    "codex marketplace manifest: bossku-ai source path must resolve to repo root"
                )
        policy = codex_entry.get("policy", {})
        if policy.get("installation") != "AVAILABLE":
            errors.append(
                "codex marketplace manifest: bossku-ai policy.installation must be AVAILABLE"
            )
        if policy.get("authentication") != "ON_INSTALL":
            errors.append(
                "codex marketplace manifest: bossku-ai policy.authentication must be ON_INSTALL"
            )

    references = opencode_data.get("references", {})
    for key, rel in (
        ("bossku-contract", "AGENTS.md"),
        ("bossku-skills", "skills"),
        ("bossku-agents", "agents"),
    ):
        ref = references.get(key)
        if not isinstance(ref, dict):
            errors.append(f"opencode config: missing references.{key}")
            continue
        if ref.get("path") != rel:
            errors.append(f"opencode config: references.{key}.path must be {rel}")
        if not _path_exists(root, rel):
            errors.append(f"opencode config: references.{key} path does not exist: {rel}")

    return errors


def claude_imports_agents_md(text: str) -> bool:
    """True if CLAUDE.md has a bare @AGENTS.md import line (Claude Code expands these)."""
    for line in text.splitlines():
        stripped = line.strip()
        if stripped == CLAUDE_AGENTS_IMPORT and "`" not in line:
            return True
    return False


def omp_imports_agents_md(text: str) -> bool:
    """True if .omp/AGENTS.md imports the canonical project AGENTS.md."""
    for line in text.splitlines():
        stripped = line.strip()
        if stripped == OMP_AGENTS_IMPORT and "`" not in line:
            return True
    return False


def validate_repo(root: Path | None = None) -> list[str]:
    errors: list[str] = []
    r = repo_root(root)
    for rel in REQUIRED_FILES:
        if not (r / rel).is_file():
            errors.append(f"missing required file: {rel}")
    claude_path = r / "CLAUDE.md"
    if claude_path.is_file():
        if not claude_imports_agents_md(claude_path.read_text(encoding="utf-8")):
            errors.append(
                "CLAUDE.md must include a bare @AGENTS.md import line for Claude Code"
            )
    omp_agents_path = r / ".omp" / "AGENTS.md"
    if omp_agents_path.is_file():
        if not omp_imports_agents_md(omp_agents_path.read_text(encoding="utf-8")):
            errors.append(
                ".omp/AGENTS.md must include a bare @../AGENTS.md import line for OMP"
            )
    agents = r / "agents"
    if not agents.is_dir():
        errors.append("missing agents/")
    else:
        for name in REQUIRED_AGENTS:
            if not (agents / name).is_file():
                errors.append(f"missing agent contract: agents/{name}")
    try:
        skills_dir(r)
    except FileNotFoundError:
        errors.append("missing skills directory")
    errors.extend(validate_skills(r))
    if not index_path(r).is_file():
        errors.append("missing skills/skill-index.json (run `bossku skills index`)")
    elif index_is_stale(r):
        errors.append(
            "skills/skill-index.json is stale for the current skills "
            "(run `bossku skills index`)"
        )
    vendored = load_vendored_ids(r)
    for sid in vendored:
        if sid not in set(list_skill_ids(r)):
            errors.append(f"vendored skill missing folder: {sid}")
    # Provenance completeness is deterministic, so it is an error. Whether a pack is
    # *due* for review depends on today's date and only ever warns - see `_stocktake`.
    provenance, _ = load_provenance(r)
    for row in pack_stocktake(r):
        meta = provenance.get(row["pack"], {})
        if not meta:
            errors.append(
                f"vendored pack {row['pack']} has no provenance entry in skills/vendored.json"
            )
        elif not row["last_synced"]:
            errors.append(f"vendored pack {row['pack']} is missing last_synced")
        elif not meta.get("upstream"):
            errors.append(f"vendored pack {row['pack']} is missing upstream")
    legacy_product = [
        "app/artisan",
        "web/package.json",
        "docker-compose.yml",
        "docker-compose.prod.yml",
        "data/postgres",
    ]
    for rel in legacy_product:
        if (r / rel).exists():
            errors.append(f"legacy product path still present: {rel}")
    errors.extend(validate_plugin_manifests(r))
    return errors
