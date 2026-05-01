#!/usr/bin/env python3
"""BosskuAI Dashboard — local-only workspace observability.

Read-mostly UI for the workspace. Renders skills as a D3 force graph,
shows memory files, exposes the vector DB, displays eval status, and
provides safe action buttons (dry-run sync, prompt generation, reindex,
eval re-run).

Run from the workspace root:
    python3 scripts/dashboard.py [--port 8765] [--host 127.0.0.1]

Open http://127.0.0.1:8765 in a browser.

Pure stdlib — no pip install needed.

Safety rules baked in:
  - Default bind is 127.0.0.1 (loopback only). Pass --host 0.0.0.0 to expose.
  - All POST endpoints are dry-run / require explicit confirm token.
  - No endpoint calls an LLM. The "understand" button writes a prompt file;
    the user runs it in their IDE.
  - Sync writes a timestamped backup before any destructive copy.
"""
from __future__ import annotations

import argparse
import datetime
import importlib.util
import io
import json
import mimetypes
import os
import re
import shutil
import sqlite3
import subprocess
import sys
import threading
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

# ---------------------------------------------------------------------------
# Paths and constants

ROOT = Path(__file__).resolve().parents[1]
DASHBOARD_DIR = ROOT / "dashboard"
SKILLS_DIR = ROOT / "ai-assistant" / "skills"
REFS_DIR = ROOT / "ai-assistant" / "references"
MEMORY_DIR = ROOT / "ai-assistant" / "memory"
SKILL_INDEX = ROOT / "skill-index.json"
VECTOR_CONFIG = MEMORY_DIR / "vector-config.json"

MARQUEE_SKILLS = {
    "cofounder",
    "bosskuai-laravel-development",
    "bosskuai-nuxt-development",
    "bosskuai-redis-caching-queues",
    "bosskuai-vps-docker-deployment",
    "bosskuai-database-engineering",
    "bosskuai-mongodb",
    "bosskuai-cybersecurity-risk",
    "bosskuai-product-strategy",
}


# ---------------------------------------------------------------------------
# Workspace introspection

def _read_skill_md(skill_id: str) -> tuple[dict, str]:
    """Parse SKILL.md frontmatter (description) and return (frontmatter, body)."""
    path = SKILLS_DIR / skill_id / "SKILL.md"
    if not path.exists():
        return {}, ""
    text = path.read_text(encoding="utf-8")
    fm = {}
    # YAML-ish frontmatter
    if text.startswith("---"):
        end = text.find("\n---", 4)
        if end > 0:
            block = text[3:end].strip()
            for line in block.splitlines():
                if ":" in line:
                    k, _, v = line.partition(":")
                    fm[k.strip()] = v.strip()
            text = text[end+4:].lstrip()
    return fm, text


def _line_count(path: Path) -> int:
    try:
        return sum(1 for _ in path.open("rb"))
    except OSError:
        return 0


def _depth_label(total: int) -> str:
    if total >= 250: return "DEEP"
    if total >= 100: return "OK"
    return "THIN"


def _category(skill_id: str) -> str:
    s = skill_id.replace("bosskuai-", "")
    if any(t in s for t in ("laravel", "nuxt", "frontend", "backend", "api-design", "engineering", "code", "rigorous", "polyglot", "documentation", "browser", "agent-security", "ai-model")): return "engineering"
    if any(t in s for t in ("docker", "vps", "deploy", "devops", "iac", "github", "operations", "ops")): return "infra"
    if any(t in s for t in ("redis", "cache", "queue")): return "runtime"
    if any(t in s for t in ("database", "mongo", "data-arch", "sql")): return "data"
    if any(t in s for t in ("security", "owasp", "auth", "cyber")): return "security"
    if any(t in s for t in ("seo", "content", "marketing", "social", "growth", "paid")): return "growth"
    if any(t in s for t in ("sales", "go-to-market", "gtm", "lead", "launch", "commercial")): return "sales"
    if any(t in s for t in ("ui", "ux", "design", "brand", "i18n", "3d", "gsap", "lenis", "smooth")): return "design"
    if any(t in s for t in ("cofounder", "execut", "operating")): return "operating"
    if any(t in s for t in ("research", "discovery", "investor", "competitor", "financial", "market", "analytics", "metrics", "customer", "deep-research", "rapid")): return "research"
    if any(t in s for t in ("test", "review", "audit", "qa", "bug", "incident", "performance", "root-cause")): return "quality"
    if any(t in s for t in ("skill-creator", "skill-stocktake", "vector", "memory", "rules", "claude-md", "workspace", "token-saver", "ask-clarifying", "search-first", "tooling", "prompt", "caveman")): return "meta"
    return "other"


def workspace_graph() -> dict:
    """Build the workspace graph for D3."""
    if not SKILL_INDEX.exists():
        return {"error": "skill-index.json missing", "nodes": [], "edges": []}
    idx = json.loads(SKILL_INDEX.read_text())
    nodes, edges = [], []

    skill_ids = {s["id"] for s in idx["skills"]}
    skill_to_node = {}

    for s in idx["skills"]:
        sid = s["id"]
        fm, body = _read_skill_md(sid)
        skill_lines = _line_count(SKILLS_DIR / sid / "SKILL.md")

        # Find referenced playbooks in body
        playbook_refs = re.findall(r'(?:playbooks/|references/playbooks/)([a-z0-9-]+\.md)', body)
        playbook_lines_max = 0
        for pb in playbook_refs:
            p = REFS_DIR / "playbooks" / pb
            if p.exists():
                playbook_lines_max = max(playbook_lines_max, _line_count(p))

        total = skill_lines + playbook_lines_max
        node = {
            "id": sid,
            "label": sid.replace("bosskuai-", ""),
            "category": _category(sid),
            "is_marquee": sid in MARQUEE_SKILLS,
            "is_core": s.get("core", False),
            "depth": _depth_label(total),
            "skill_lines": skill_lines,
            "playbook_lines": playbook_lines_max,
            "total_lines": total,
            "triggers": s.get("triggers", []),
            "keywords": s.get("keywords", []),
            "trigger_count": len(s.get("triggers", [])),
            "description": fm.get("description", "")[:300],
            "playbook_refs": playbook_refs,
        }
        nodes.append(node)
        skill_to_node[sid] = node

    # Build edges from cross-references in SKILL.md bodies
    seen = set()
    for src in idx["skills"]:
        sid = src["id"]
        _, body = _read_skill_md(sid)
        for other in skill_ids:
            if other == sid: continue
            # match `bosskuai-foo` or quoted skill ids
            if re.search(r'\b' + re.escape(other) + r'\b', body):
                key = (sid, other)
                if key in seen: continue
                seen.add(key)
                edges.append({"source": sid, "target": other, "kind": "cross_ref"})

    # Trigger overlap edges
    inv = {}
    for s in idx["skills"]:
        for t in s.get("triggers", []) + s.get("keywords", []):
            inv.setdefault(t.lower().strip(), set()).add(s["id"])
    for term, sids in inv.items():
        if len(sids) > 1:
            sids_l = sorted(sids)
            for i in range(len(sids_l)):
                for j in range(i+1, len(sids_l)):
                    key = (sids_l[i], sids_l[j], "overlap")
                    if key in seen: continue
                    seen.add(key)
                    edges.append({"source": sids_l[i], "target": sids_l[j], "kind": "overlap"})

    # Category counts
    by_cat = {}
    for n in nodes:
        by_cat.setdefault(n["category"], 0)
        by_cat[n["category"]] += 1

    return {
        "version": idx.get("version", "?"),
        "node_count": len(nodes),
        "edge_count": len(edges),
        "categories": by_cat,
        "nodes": nodes,
        "edges": edges,
    }


# ---------------------------------------------------------------------------
# Memory

def memory_files() -> dict:
    if not MEMORY_DIR.exists():
        return {"files": []}
    files = []
    for p in sorted(MEMORY_DIR.iterdir()):
        if not p.is_file(): continue
        if p.suffix not in (".md", ".json"): continue
        try:
            text = p.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            text = "<binary>"
        files.append({
            "name": p.name,
            "size": p.stat().st_size,
            "lines": text.count("\n") + 1 if text != "<binary>" else 0,
            "modified": datetime.datetime.fromtimestamp(p.stat().st_mtime).isoformat(timespec="seconds"),
            "content": text,
        })
    return {"files": files}


# ---------------------------------------------------------------------------
# Vector DB

def _vector_db_path() -> Path | None:
    if not VECTOR_CONFIG.exists(): return None
    cfg = json.loads(VECTOR_CONFIG.read_text())
    db_rel = cfg.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3")
    return ROOT / db_rel


def vectordb_status() -> dict:
    db_path = _vector_db_path()
    if db_path is None:
        return {"status": "no_config", "message": "vector-config.json missing"}
    if not db_path.exists():
        return {"status": "not_built",
                "message": "DB not yet built. Run: python ai-assistant/scripts/vector_memory.py reindex",
                "db_path": str(db_path.relative_to(ROOT))}
    try:
        conn = sqlite3.connect(f"file:{db_path}?mode=ro", uri=True)
        try:
            tables = [r[0] for r in conn.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()]
            stats = {"db_path": str(db_path.relative_to(ROOT)), "tables": tables, "size_bytes": db_path.stat().st_size}
            for t in tables:
                try:
                    stats[f"count:{t}"] = conn.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0]
                except sqlite3.OperationalError:
                    pass
            # If there's a chunks table, show source distribution
            if "chunks" in tables:
                try:
                    rows = conn.execute("SELECT source, COUNT(*) FROM chunks GROUP BY source ORDER BY 2 DESC LIMIT 30").fetchall()
                    stats["top_sources"] = [{"source": r[0], "count": r[1]} for r in rows]
                except sqlite3.OperationalError:
                    pass
            return {"status": "ready", **stats}
        finally:
            conn.close()
    except sqlite3.Error as e:
        return {"status": "error", "message": str(e)}


def _import_vector_memory():
    spec = importlib.util.spec_from_file_location("vector_memory", ROOT / "ai-assistant/scripts/vector_memory.py")
    mod = importlib.util.module_from_spec(spec)
    sys.modules["vector_memory"] = mod
    assert spec.loader is not None
    spec.loader.exec_module(mod)
    return mod


def vectordb_query(query: str, top_k: int = 8) -> dict:
    """Run a retrieval query through the production vector_memory scorer
    against the persistent on-disk DB."""
    db_path = _vector_db_path()
    if not db_path or not db_path.exists():
        return {"error": "DB not built. Click 'Reindex' first."}
    try:
        vm = _import_vector_memory()
    except Exception as e:
        return {"error": f"failed to import vector_memory: {e}"}
    try:
        cfg, _ = vm.load_config(ROOT, None)
        embedder = vm.build_embedder(cfg)
        conn = vm.connect(db_path)
        try:
            # Hydrate any corpus-level state the embedder needs (e.g. TF-IDF idf)
            try:
                row = conn.execute("SELECT value FROM meta WHERE key = ?", ("corpus_info",)).fetchone()
                if row and hasattr(embedder, "load_corpus_info"):
                    embedder.load_corpus_info(row[0])
            except sqlite3.OperationalError:
                pass

            hits = vm.retrieve_from_conn(conn, embedder, query, cfg, limit=top_k)
            results = []
            for h in hits:
                d = {
                    "path": h.get("path"),
                    "score": round(float(h.get("score", 0)), 3),
                    "heading": h.get("heading"),
                    "ordinal": h.get("ordinal"),
                    "components": {k: round(float(v), 3) for k, v in (h.get("components") or {}).items()},
                    "preview": (h.get("content") or "")[:280],
                }
                results.append(d)
            return {"query": query, "top_k": top_k, "results": results}
        finally:
            conn.close()
    except Exception as e:
        return {"error": f"retrieval failed: {type(e).__name__}: {e}", "query": query}


# ---------------------------------------------------------------------------
# Evals

EVAL_SCRIPTS = [
    ("workspace", "scripts/eval_workspace.py", []),
    ("expert_coverage", "scripts/eval_expert_coverage.py", []),
    ("adversarial_routing", "scripts/eval_adversarial_routing.py", []),
    ("routing_generalization", "scripts/eval_routing_generalization.py", []),
]

PLUGIN_EVAL_PACKAGE = ROOT / "packages" / "bossku-ai"


def _plugin_eval_command(target: Path) -> list[str] | None:
    """Return a runnable plugin-eval command for this local machine."""
    cli = shutil.which("plugin-eval")
    if cli:
        return [cli, "analyze", str(target), "--format", "markdown"]

    script = (
        Path.home()
        / ".codex"
        / "plugins"
        / "cache"
        / "openai-curated"
        / "plugin-eval"
        / "6807e4de"
        / "scripts"
        / "plugin-eval.js"
    )
    if script.exists() and shutil.which("node"):
        return ["node", str(script), "analyze", str(target), "--format", "markdown"]
    return None


def _headline_from_plugin_eval(output: str) -> str:
    score = grade = risk = ""
    for line in output.splitlines():
        stripped = line.strip()
        if stripped.startswith("- Score:"):
            score = score or stripped.removeprefix("- Score:").strip()
        elif stripped.startswith("- Grade:"):
            grade = grade or stripped.removeprefix("- Grade:").strip()
        elif stripped.startswith("- Risk:"):
            risk = risk or stripped.removeprefix("- Risk:").strip()
    parts = [
        part
        for part in (
            f"Score {score}" if score else "",
            f"Grade {grade}" if grade else "",
            f"Risk {risk}" if risk else "",
        )
        if part
    ]
    return " · ".join(parts) if parts else (output.splitlines()[0][:120] if output else "")


def run_plugin_package_eval() -> dict:
    target_rel = str(PLUGIN_EVAL_PACKAGE.relative_to(ROOT))
    if not PLUGIN_EVAL_PACKAGE.exists():
        return {"status": "missing", "target": target_rel}
    command = _plugin_eval_command(PLUGIN_EVAL_PACKAGE)
    if command is None:
        return {
            "status": "missing",
            "target": target_rel,
            "headline": "plugin-eval CLI not found",
            "output_tail": "Install or expose plugin-eval, or keep the local Codex plugin-eval cache available.",
        }
    try:
        p = subprocess.run(command, cwd=str(ROOT), capture_output=True, text=True, timeout=120)
        output = p.stdout
        return {
            "status": "pass" if p.returncode == 0 else "fail",
            "exit": p.returncode,
            "target": target_rel,
            "command": " ".join(command),
            "headline": _headline_from_plugin_eval(output),
            "output_tail": "\n".join(output.splitlines()[-12:]),
        }
    except subprocess.TimeoutExpired:
        return {"status": "timeout", "target": target_rel}
    except Exception as e:
        return {"status": "error", "target": target_rel, "message": str(e)}


def run_evals() -> dict:
    results = {}
    for name, script, args in EVAL_SCRIPTS:
        path = ROOT / script
        if not path.exists():
            results[name] = {"status": "missing", "script": script}
            continue
        wants_json = name in ("adversarial_routing", "routing_generalization")
        try:
            cmd = [sys.executable, "-S", str(path)]
            if wants_json: cmd.append("--json")
            p = subprocess.run(
                cmd, cwd=str(ROOT), capture_output=True, text=True, timeout=120,
            )
            output = p.stdout

            # Headline extraction: parse JSON for routing evals; grep for the rest
            headline = ""
            if wants_json and output.strip().startswith("{"):
                try:
                    j = json.loads(output)
                    payload = next(iter(j.values())) if j else {}
                    headline = (
                        f"{payload.get('passed', '?')}/{payload.get('total', '?')} "
                        f"({int(round((payload.get('pass_rate') or 0) * 100))}%) "
                        f"{'GREEN' if payload.get('green') else 'RED'}"
                    )
                except json.JSONDecodeError:
                    headline = output.splitlines()[0][:120] if output else ""
            else:
                for marker in ("score:", "Score:"):
                    line = next((l for l in output.splitlines() if marker in l), None)
                    if line:
                        headline = line.strip()
                        break
                if not headline and output:
                    headline = output.splitlines()[0][:120]

            results[name] = {
                "status": "pass" if p.returncode == 0 else "fail",
                "exit": p.returncode,
                "headline": headline[:200],
                "output_tail": "\n".join(output.splitlines()[-12:]),
            }
        except subprocess.TimeoutExpired:
            results[name] = {"status": "timeout"}
        except Exception as e:
            results[name] = {"status": "error", "message": str(e)}
    results["codex_plugin_package"] = run_plugin_package_eval()
    return results


# ---------------------------------------------------------------------------
# Action: generate project-understanding prompt

def generate_understand_prompt(target_path: str) -> dict:
    """Validate path, write a framed prompt + skill content to <target>/.bosskuai-understand-prompt.md.
       No LLM call. The user runs the prompt in Claude Code themselves.
    """
    if not target_path or target_path.strip() in ("/", ""):
        return {"ok": False, "error": "target path required"}
    target = Path(target_path).expanduser().resolve()
    if not target.exists() or not target.is_dir():
        return {"ok": False, "error": f"path does not exist or is not a directory: {target}"}
    if str(target) == str(ROOT):
        return {"ok": False, "error": "refusing to operate on the source workspace itself"}

    skill_md_path = SKILLS_DIR / "bosskuai-project-understanding" / "SKILL.md"
    if not skill_md_path.exists():
        return {"ok": False, "error": "bosskuai-project-understanding skill not found"}
    skill_text = skill_md_path.read_text(encoding="utf-8")

    # List top-level entries (lightweight, not full file contents)
    entries = []
    for p in sorted(target.iterdir())[:80]:
        if p.name.startswith("."): continue
        kind = "dir" if p.is_dir() else "file"
        entries.append(f"- {kind}: {p.name}")

    framed = f"""# Project Understanding — Generated Prompt

Generated by BosskuAI dashboard at {datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds")}.

Target path: `{target}`

## How to use this file

In Claude Code (or your preferred Bossku-aware runtime), open this file and ask:
"Apply the bosskuai-project-understanding skill to this codebase. Use the framing below.
Output goes to `ai-assistant/memory/project-understanding.md` per the skill's convention."

## Skill content

{skill_text}

## Target — top-level entries

{chr(10).join(entries)}

## Framing for the agent

- The user wants a durable summary of what this codebase is, the stack, the source-of-truth files, and the most relevant Bossku specialist skills for future work in this repo.
- Read the manifest files (package.json, composer.json, pyproject.toml, etc.) before guessing.
- For Laravel-shape repos, check `composer.json`, `routes/`, `app/Models`, `database/migrations`.
- For Nuxt-shape, check `nuxt.config.*`, `pages/`, `app/`, `server/api/`.
- Output the standard project-understanding format from the skill.
- If anything is ambiguous, list the ambiguity rather than guessing.
"""
    out_path = target / ".bosskuai-understand-prompt.md"
    out_path.write_text(framed, encoding="utf-8")
    return {
        "ok": True,
        "wrote": str(out_path),
        "size": out_path.stat().st_size,
        "next_step": f"Open this file in Claude Code and run it: {out_path}",
    }


# ---------------------------------------------------------------------------
# Action: sync skills to a target path

def _sync_plan(target: Path, scope: str) -> list[dict]:
    """Decide which files would be copied. Return a list of {src, dst, action, reason}."""
    plans = []

    # Common files for all scopes
    common = [
        "AGENTS.md",
        "CLAUDE.md",
        "skill-index.json",
        ".claude/rules",
        ".cursor/rules",
        ".codex/AGENTS.md",
    ]

    skills_to_copy = []
    if scope == "marquee":
        skills_to_copy = sorted(MARQUEE_SKILLS)
    elif scope == "full":
        if SKILLS_DIR.exists():
            skills_to_copy = sorted(p.name for p in SKILLS_DIR.iterdir() if p.is_dir())
    elif scope.startswith("custom:"):
        ids = [s.strip() for s in scope.split(":", 1)[1].split(",") if s.strip()]
        skills_to_copy = ids

    refs_to_copy = []
    if scope == "full":
        refs_to_copy = ["ai-assistant/references"]
    elif scope == "marquee":
        # Pull only the marquee playbooks + their detailed counterparts
        marquee_pbs = []
        for sid in MARQUEE_SKILLS:
            _, body = _read_skill_md(sid)
            for pb in re.findall(r'([a-z0-9-]+(?:-detailed)?-playbook\.md)', body):
                marquee_pbs.append(f"ai-assistant/references/playbooks/{pb}")
        marquee_pbs.append("ai-assistant/references/playbooks/cofounder-decision-quality-playbook.md")
        marquee_pbs.append("ai-assistant/references/playbooks/bosskuai-product-strategy-playbook.md")
        refs_to_copy = sorted(set(marquee_pbs))

    for rel in common:
        src = ROOT / rel
        if not src.exists(): continue
        dst = target / rel
        plans.append(_plan_entry(src, dst))

    for sid in skills_to_copy:
        src = SKILLS_DIR / sid
        if not src.exists():
            plans.append({"src": str(src.relative_to(ROOT)), "dst": "", "action": "skip", "reason": "source missing"})
            continue
        dst = target / "ai-assistant" / "skills" / sid
        plans.append(_plan_entry(src, dst))

    for rel in refs_to_copy:
        src = ROOT / rel
        if not src.exists(): continue
        dst = target / rel
        plans.append(_plan_entry(src, dst))

    return plans


def _plan_entry(src: Path, dst: Path) -> dict:
    rel_src = str(src.relative_to(ROOT))
    if not dst.exists():
        return {"src": rel_src, "dst": str(dst), "action": "create", "reason": "target does not exist"}
    # Compare hash for files; mtime for dirs
    if src.is_file() and dst.is_file():
        if src.read_bytes() == dst.read_bytes():
            return {"src": rel_src, "dst": str(dst), "action": "skip", "reason": "identical"}
        # Check for protect marker
        try:
            content = dst.read_text(encoding="utf-8")
            if "DO NOT SYNC" in content:
                return {"src": rel_src, "dst": str(dst), "action": "skip", "reason": "DO NOT SYNC marker present"}
        except UnicodeDecodeError:
            pass
        return {"src": rel_src, "dst": str(dst), "action": "overwrite", "reason": "content differs"}
    return {"src": rel_src, "dst": str(dst), "action": "overwrite", "reason": "dir or mixed"}


def sync_dry_run(target_path: str, scope: str) -> dict:
    if not target_path or target_path.strip() in ("/", ""):
        return {"ok": False, "error": "target path required"}
    target = Path(target_path).expanduser().resolve()
    if not target.exists() or not target.is_dir():
        return {"ok": False, "error": f"target path does not exist or is not a directory: {target}"}
    if str(target) == str(ROOT):
        return {"ok": False, "error": "refusing to sync to the source workspace itself"}
    if str(ROOT).startswith(str(target) + os.sep):
        return {"ok": False, "error": "refusing to sync to a parent of the source workspace"}

    plans = _sync_plan(target, scope)
    summary = {"create": 0, "overwrite": 0, "skip": 0}
    for p in plans:
        summary[p["action"]] = summary.get(p["action"], 0) + 1

    # Confirm token: must be passed back to /api/sync/apply
    payload = {"target": str(target), "scope": scope, "plans": plans}
    confirm_token = _confirm_token(payload)
    return {"ok": True, "summary": summary, "plans": plans, "confirm_token": confirm_token,
            "target": str(target), "scope": scope}


def _confirm_token(payload: dict) -> str:
    import hashlib
    h = hashlib.sha256(json.dumps(payload, sort_keys=True).encode()).hexdigest()
    return h[:16]


def sync_apply(target_path: str, scope: str, confirm_token: str) -> dict:
    plan = sync_dry_run(target_path, scope)
    if not plan.get("ok"):
        return plan
    if plan["confirm_token"] != confirm_token:
        return {"ok": False, "error": "confirm token mismatch — re-run dry-run and confirm"}
    target = Path(plan["target"])
    plans = plan["plans"]

    # Backup
    ts = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup_root = target / ".bosskuai-backup" / ts
    backup_root.mkdir(parents=True, exist_ok=True)

    applied, errors = [], []
    for p in plans:
        if p["action"] == "skip": continue
        src = ROOT / p["src"]
        dst = Path(p["dst"])
        try:
            # Backup destination if it exists
            if dst.exists():
                rel = dst.relative_to(target)
                bdst = backup_root / rel
                bdst.parent.mkdir(parents=True, exist_ok=True)
                if dst.is_dir():
                    shutil.copytree(dst, bdst, dirs_exist_ok=True)
                else:
                    shutil.copy2(dst, bdst)
            # Apply
            dst.parent.mkdir(parents=True, exist_ok=True)
            if src.is_dir():
                shutil.copytree(src, dst, dirs_exist_ok=True)
            else:
                shutil.copy2(src, dst)
            applied.append({"src": p["src"], "dst": str(dst), "action": p["action"]})
        except Exception as e:
            errors.append({"src": p["src"], "dst": str(dst), "error": str(e)})
    return {"ok": True, "applied": len(applied), "errors": errors, "backup_dir": str(backup_root)}


# ---------------------------------------------------------------------------
# Action: vector reindex

def vectordb_reindex() -> dict:
    script = ROOT / "ai-assistant/scripts/vector_memory.py"
    if not script.exists():
        return {"ok": False, "error": "vector_memory.py not found"}
    try:
        # vector_memory.py uses 'sync' as the build/refresh subcommand.
        p = subprocess.run(
            [sys.executable, str(script), "sync"],
            cwd=str(ROOT), capture_output=True, text=True, timeout=300,
        )
        return {
            "ok": p.returncode == 0,
            "exit": p.returncode,
            "stdout_tail": "\n".join(p.stdout.splitlines()[-25:]),
            "stderr_tail": "\n".join(p.stderr.splitlines()[-10:]),
        }
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "reindex timeout (>300s)"}
    except Exception as e:
        return {"ok": False, "error": str(e)}


# ---------------------------------------------------------------------------
# HTTP handler

class DashboardHandler(BaseHTTPRequestHandler):
    server_version = "BosskuAI-Dashboard/1.0"

    def log_message(self, format, *args):
        # Quieter than default
        sys.stderr.write(f"[dash] {self.address_string()} {format%args}\n")

    def _json(self, status: int, payload):
        body = json.dumps(payload, default=str, indent=2).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _serve_static(self, rel: str):
        if rel == "" or rel == "/":
            rel = "index.html"
        rel = rel.lstrip("/")
        # safety: no path traversal
        full = (DASHBOARD_DIR / rel).resolve()
        try:
            full.relative_to(DASHBOARD_DIR.resolve())
        except ValueError:
            self.send_error(403, "forbidden")
            return
        if not full.exists() or not full.is_file():
            self.send_error(404, "not found")
            return
        ctype = mimetypes.guess_type(str(full))[0] or "application/octet-stream"
        body = full.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        url = urllib.parse.urlparse(self.path)
        path = url.path
        qs = urllib.parse.parse_qs(url.query)

        try:
            if path == "/api/workspace":
                return self._json(200, workspace_graph())
            if path == "/api/memory":
                return self._json(200, memory_files())
            if path == "/api/vectordb":
                return self._json(200, vectordb_status())
            if path == "/api/vectordb/query":
                q = (qs.get("q", [""])[0]).strip()
                k = int(qs.get("k", ["8"])[0])
                if not q:
                    return self._json(400, {"error": "q required"})
                return self._json(200, vectordb_query(q, top_k=k))
            if path == "/api/evals":
                return self._json(200, run_evals())
            if path == "/api/health":
                return self._json(200, {"ok": True, "root": str(ROOT)})
            return self._serve_static(path)
        except Exception as e:
            return self._json(500, {"error": str(e), "type": type(e).__name__})

    def do_POST(self):
        url = urllib.parse.urlparse(self.path)
        path = url.path
        length = int(self.headers.get("Content-Length", "0"))
        body = self.rfile.read(length) if length > 0 else b""
        try:
            payload = json.loads(body) if body else {}
        except json.JSONDecodeError:
            return self._json(400, {"error": "invalid JSON body"})

        try:
            if path == "/api/understand":
                return self._json(200, generate_understand_prompt(payload.get("target_path", "")))
            if path == "/api/sync/dry-run":
                return self._json(200, sync_dry_run(payload.get("target_path", ""), payload.get("scope", "marquee")))
            if path == "/api/sync/apply":
                return self._json(200, sync_apply(payload.get("target_path", ""), payload.get("scope", "marquee"), payload.get("confirm_token", "")))
            if path == "/api/vectordb/reindex":
                return self._json(200, vectordb_reindex())
            return self._json(404, {"error": "not found"})
        except Exception as e:
            return self._json(500, {"error": str(e), "type": type(e).__name__})


# ---------------------------------------------------------------------------
# Main

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=8765)
    args = ap.parse_args()

    server = ThreadingHTTPServer((args.host, args.port), DashboardHandler)
    url = f"http://{args.host}:{args.port}/"
    print(f"BosskuAI Dashboard")
    print(f"  workspace: {ROOT}")
    print(f"  serving:   {url}")
    print(f"  bind:      {args.host} ({'loopback only' if args.host == '127.0.0.1' else 'EXPOSED — ensure firewall'})")
    print(f"  Ctrl-C to stop.")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nshutting down")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
