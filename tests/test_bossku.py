import io
import json
import os
import stat
import tempfile
import unittest
from contextlib import redirect_stdout
from pathlib import Path
from unittest import mock

import bossku
from bossku.cli import _doctor, main
from bossku.doctor import gather_doctor_issues
from bossku.hooks import (
    CLAUDE_EVENTS,
    CODEX_EVENTS,
    CURSOR_EVENTS,
    HOOK_MARKER,
    hooks_status,
    install_hooks,
    run_sync_hook,
    uninstall_hooks,
)
from bossku.init_project import init_project, upsert_managed_block
from bossku.install import install_user, uninstall_user
from bossku.memory import remember, sync_project
from bossku.paths import user_config_dir
from bossku.redact import redact
from bossku.skills import (
    count_managed_skills,
    find_skill,
    is_managed_skill_name,
    load_vendored_ids,
    resolve_skill_id,
)
from bossku.validate import (
    claude_imports_agents_md,
    omp_imports_agents_md,
    package_version,
    validate_plugin_manifests,
    validate_repo,
)


ROOT = Path(__file__).resolve().parents[1]


class RedactTests(unittest.TestCase):
    def test_redacts_api_key(self):
        text = "api_key=supersecret123"
        self.assertIn("[REDACTED]", redact(text))


class MarkerTests(unittest.TestCase):
    def test_upsert_managed_block_idempotent(self):
        block = "BosskuAI active"
        first = upsert_managed_block("", block)
        second = upsert_managed_block(first, block)
        self.assertEqual(first.count("<!-- bosskuai:start -->"), 1)
        self.assertEqual(second.count("<!-- bosskuai:start -->"), 1)


class InstallTests(unittest.TestCase):
    @staticmethod
    def _set_tree_writable(path: Path) -> None:
        if not path.exists():
            return
        for child in path.rglob("*"):
            os.chmod(child, stat.S_IREAD | stat.S_IWRITE | stat.S_IEXEC)
        os.chmod(path, stat.S_IREAD | stat.S_IWRITE | stat.S_IEXEC)

    def test_install_and_uninstall_user_skills(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            result = install_user(root=ROOT, home=home, profile="core")
            self.assertGreater(result["installed_count"], 0)
            self.assertEqual(result["agents_count"], result["claude_count"])
            self.assertIn("tools", result)
            for key in ("cursor", "codex", "opencode", "claude_code", "omp"):
                self.assertIn(key, result["tools"])
            agents = home / ".agents" / "skills"
            claude = home / ".claude" / "skills"
            self.assertTrue(agents.is_dir())
            self.assertTrue(claude.is_dir())
            self.assertEqual(
                count_managed_skills(agents, ROOT),
                count_managed_skills(claude, ROOT),
            )
            for sid in (
                "loop-triage",
                "minimal-fix",
                "ci-triage",
                "loop-verifier",
                "bosskuai-grounding",
            ):
                self.assertTrue((agents / sid).is_dir(), msg=f"missing core skill {sid}")
            removed = uninstall_user(root=ROOT, home=home)
            self.assertTrue(len(removed["removed_skills"]) >= 0)

    def test_full_install_includes_vendored_packs(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            install_user(root=ROOT, home=home, profile="full")
            agents = home / ".agents" / "skills"
            for sid in (
                "product-marketing",
                "brainstorming",
                "hallmark",
                "graphify",
                "graft",
                "browser-use",
                "markitdown",
                "dcg",
                "antislop",
                "antislop-ui",
                "bosskuai-headroom",
                "odl-pdf",
            ):
                self.assertTrue((agents / sid).is_dir(), msg=f"missing {sid}")
            uninstall_user(root=ROOT, home=home)

    def test_reinstall_and_uninstall_handle_read_only_skill_trees(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            install_user(root=ROOT, home=home, profile="core")
            agents_skill = home / ".agents" / "skills" / "cofounder"
            try:
                for child in agents_skill.rglob("*"):
                    os.chmod(child, stat.S_IREAD)
                os.chmod(agents_skill, stat.S_IREAD)

                install_user(root=ROOT, home=home, profile="core")
                self.assertTrue((agents_skill / "SKILL.md").is_file())
                uninstall_user(root=ROOT, home=home)
                self.assertFalse(agents_skill.exists())
            finally:
                self._set_tree_writable(home)

    def test_install_copies_shared_references_for_both_skill_roots(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            result = install_user(root=ROOT, home=home, profile="core")

            relative = Path("checklists") / "skill-health-checklist.md"
            self.assertTrue((home / ".agents" / "references" / relative).is_file())
            self.assertTrue((home / ".claude" / "references" / relative).is_file())
            self.assertGreater(result["agents_reference_count"], 0)
            self.assertEqual(result["agents_reference_count"], result["claude_reference_count"])


class VendoredTests(unittest.TestCase):
    def test_vendored_ids_loaded(self):
        ids = load_vendored_ids(ROOT)
        self.assertIn("copywriting", ids)
        self.assertIn("brainstorming", ids)
        self.assertIn("hallmark", ids)
        self.assertIn("antislop", ids)
        self.assertIn("antislop-ui", ids)
        self.assertIn("odl-pdf", ids)
        self.assertGreaterEqual(len(ids), 68)

    def test_managed_vendored_skill_name(self):
        self.assertTrue(is_managed_skill_name("copywriting", ROOT))
        self.assertTrue(is_managed_skill_name("bosskuai-taste", ROOT))
        self.assertFalse(is_managed_skill_name("some-random-skill", ROOT))


class InitTests(unittest.TestCase):
    def test_init_preserves_existing_agents(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = Path(tmp) / "demo"
            project.mkdir()
            agents = project / "AGENTS.md"
            agents.write_text("# Custom\n\nKeep this line.\n", encoding="utf-8")
            init_project(project, root=ROOT)
            text = agents.read_text(encoding="utf-8")
            self.assertIn("Keep this line.", text)
            self.assertIn("bosskuai:start", text)
            self.assertIn("complementary", text)
            self.assertIn("Anti-Slop", text)
            self.assertIn("insufficient", text)
            self.assertIn("verify", text)
            self.assertTrue((project / ".bossku" / "memory" / "project.md").is_file())
            metadata = json.loads((project / ".bossku" / "project.json").read_text(encoding="utf-8"))
            self.assertEqual(metadata["bossku_version"], bossku.__version__)
            claude = (project / "CLAUDE.md").read_text(encoding="utf-8")
            self.assertTrue(claude_imports_agents_md(claude))
            omp_agents = (project / ".omp" / "AGENTS.md").read_text(encoding="utf-8")
            self.assertTrue(omp_imports_agents_md(omp_agents))
            omp_config = (project / ".omp" / "config.yml").read_text(encoding="utf-8")
            self.assertIn("approvalMode: write", omp_config)

    def test_grounding_surfaces_stay_in_sync(self):
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        cursor_rule = (ROOT / ".cursor" / "rules" / "bosskuai.mdc").read_text(encoding="utf-8")
        init_src = (ROOT / "bossku" / "init_project.py").read_text(encoding="utf-8")
        self.assertIn("Grounding", agents)
        self.assertIn("Grounding", cursor_rule)
        self.assertIn("Grounding", init_src)
        self.assertTrue((ROOT / "skills" / "bosskuai-grounding" / "SKILL.md").is_file())

    def test_init_writes_claude_import_on_empty_project(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = Path(tmp) / "fresh"
            init_project(project, root=ROOT)
            claude = (project / "CLAUDE.md").read_text(encoding="utf-8")
            self.assertTrue(claude_imports_agents_md(claude))
            self.assertTrue((project / "AGENTS.md").is_file())
            omp_agents = (project / ".omp" / "AGENTS.md").read_text(encoding="utf-8")
            self.assertTrue(omp_imports_agents_md(omp_agents))

    def test_init_refreshes_old_project_metadata_without_losing_fields(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = Path(tmp) / "existing"
            meta = project / ".bossku"
            meta.mkdir(parents=True)
            (meta / "project.json").write_text(
                json.dumps({"bossku_version": "2.0.0", "profile": "full", "keep": True}),
                encoding="utf-8",
            )
            init_project(project, root=ROOT)
            payload = json.loads((meta / "project.json").read_text(encoding="utf-8"))
            self.assertEqual(payload["bossku_version"], bossku.__version__)
            self.assertEqual(payload["profile"], "full")
            self.assertTrue(payload["keep"])


class MemoryTests(unittest.TestCase):
    def test_remember_and_sync_offline_vault(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            project = home / "proj"
            project.mkdir()
            init_project(project, root=ROOT)
            result = remember(project, "decision", "Ship toolkit-only main.", home=home)
            self.assertEqual(result["kind"], "decision")
            sync = sync_project(project, home=home)
            self.assertEqual(sync["status"], "skipped")


class HooksTests(unittest.TestCase):
    def test_install_skips_tools_not_present(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            result = install_hooks(home=home)
            for tool in ("claude_code", "cursor", "codex", "opencode"):
                self.assertEqual(result[tool]["status"], "skipped_not_found")
            self.assertFalse((home / ".claude").exists())

    def test_install_preserves_existing_unrelated_hook(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            claude_dir = home / ".claude"
            claude_dir.mkdir(parents=True)
            existing = {
                "hooks": {
                    "Stop": [
                        {"hooks": [{"type": "command", "command": "echo unrelated-hook"}]}
                    ]
                }
            }
            (claude_dir / "settings.json").write_text(json.dumps(existing, indent=2), encoding="utf-8")

            result = install_hooks(home=home, tools=("claude_code",))
            self.assertEqual(result["claude_code"]["status"], "installed")

            data = json.loads((claude_dir / "settings.json").read_text(encoding="utf-8"))
            stop = data["hooks"]["Stop"]
            self.assertEqual(len(stop), 2)
            dumped = json.dumps(stop)
            self.assertIn("echo unrelated-hook", dumped)
            self.assertIn(HOOK_MARKER, dumped)

            backups = list(claude_dir.glob("settings.json.bak-*"))
            self.assertEqual(len(backups), 1)

    def test_install_is_idempotent(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            (home / ".cursor").mkdir(parents=True)

            first = install_hooks(home=home, tools=("cursor",))
            self.assertEqual(first["cursor"]["status"], "installed")
            before = (home / ".cursor" / "hooks.json").read_text(encoding="utf-8")

            second = install_hooks(home=home, tools=("cursor",))
            self.assertEqual(second["cursor"]["status"], "already_installed")
            after = (home / ".cursor" / "hooks.json").read_text(encoding="utf-8")
            self.assertEqual(before, after)

    def test_uninstall_removes_only_bossku_entry(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            codex_dir = home / ".codex"
            codex_dir.mkdir(parents=True)
            existing = {"hooks": {"Stop": [{"hooks": [{"type": "command", "command": "echo unrelated"}]}]}}
            (codex_dir / "hooks.json").write_text(json.dumps(existing, indent=2), encoding="utf-8")

            install_hooks(home=home, tools=("codex",))
            result = uninstall_hooks(home=home, tools=("codex",))
            self.assertEqual(result["codex"]["status"], "removed")

            data = json.loads((codex_dir / "hooks.json").read_text(encoding="utf-8"))
            dumped = json.dumps(data["hooks"]["Stop"])
            self.assertIn("echo unrelated", dumped)
            self.assertNotIn(HOOK_MARKER, dumped)

    def test_install_all_four_tools(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            (home / ".claude").mkdir(parents=True)
            (home / ".cursor").mkdir(parents=True)
            (home / ".codex").mkdir(parents=True)
            (home / ".config" / "opencode").mkdir(parents=True)

            result = install_hooks(home=home)
            for tool in ("claude_code", "cursor", "codex", "opencode"):
                self.assertEqual(result[tool]["status"], "installed", msg=f"{tool}: {result[tool]}")

            status = hooks_status(home)
            for tool in ("claude_code", "cursor", "codex", "opencode"):
                self.assertTrue(status[tool], msg=f"{tool} not reported installed")

            plugin = (home / ".config" / "opencode" / "plugins" / "bossku-sync.js").read_text(encoding="utf-8")
            self.assertIn("session.idle", plugin)
            self.assertIn("sync-hook", plugin)

    def test_sync_hook_reads_cwd_from_stdin(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            project = home / "proj"
            project.mkdir()
            init_project(project, root=ROOT)

            fake_stdin = io.StringIO(json.dumps({"cwd": str(project)}))
            with mock.patch("sys.stdin", fake_stdin):
                result = run_sync_hook(home=home)
            self.assertEqual(result["status"], "skipped")

    def test_sync_hook_falls_back_to_explicit_project(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            project = home / "proj"
            project.mkdir()
            init_project(project, root=ROOT)
            result = run_sync_hook(project=project, home=home)
            self.assertEqual(result["status"], "skipped")


    def test_cursor_installs_denser_events(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            (home / ".cursor").mkdir(parents=True)
            result = install_hooks(home=home, tools=("cursor",))
            self.assertEqual(result["cursor"]["status"], "installed")
            data = json.loads((home / ".cursor" / "hooks.json").read_text(encoding="utf-8"))
            for event in CURSOR_EVENTS:
                self.assertIn(event, data["hooks"])
                self.assertTrue(any(HOOK_MARKER in json.dumps(e) for e in data["hooks"][event]))
            self.assertTrue(hooks_status(home)["cursor"])

    def test_claude_installs_stop_and_session_end(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            (home / ".claude").mkdir(parents=True)
            result = install_hooks(home=home, tools=("claude_code",))
            self.assertEqual(result["claude_code"]["status"], "installed")
            data = json.loads((home / ".claude" / "settings.json").read_text(encoding="utf-8"))
            for event in CLAUDE_EVENTS:
                self.assertIn(event, data["hooks"])
                self.assertTrue(any(HOOK_MARKER in json.dumps(e) for e in data["hooks"][event]))

    def test_claude_repairs_unquoted_windows_command(self):
        # Claude Code runs hooks through Git Bash on Windows, where an unquoted
        # `C:\...\bossku.EXE` loses its backslashes and the sync never runs.
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            claude_dir = home / ".claude"
            claude_dir.mkdir(parents=True)
            broken = r"C:\Users\x\Scripts\bossku.EXE sync-hook"
            legacy = {
                "hooks": {
                    "Stop": [
                        {"hooks": [{"type": "command", "command": "echo unrelated-hook"}]},
                        {"hooks": [{"type": "command", "command": broken}]},
                    ],
                    "SessionEnd": [{"hooks": [{"type": "command", "command": broken}]}],
                }
            }
            (claude_dir / "settings.json").write_text(json.dumps(legacy), encoding="utf-8")

            result = install_hooks(home=home, tools=("claude_code",))
            self.assertEqual(result["claude_code"]["status"], "installed")
            data = json.loads((claude_dir / "settings.json").read_text(encoding="utf-8"))
            for event in CLAUDE_EVENTS:
                commands = [h["command"] for e in data["hooks"][event] for h in e["hooks"]]
                synced = [c for c in commands if HOOK_MARKER in c]
                self.assertEqual(len(synced), 1, commands)
                self.assertTrue(synced[0].startswith('"'), synced[0])
                self.assertNotIn("\\", synced[0])
            self.assertIn("echo unrelated-hook", json.dumps(data["hooks"]["Stop"]))
            again = install_hooks(home=home, tools=("claude_code",))
            self.assertEqual(again["claude_code"]["status"], "already_installed")

    def test_cursor_upgrade_adds_missing_denser_events(self):
        """Legacy stop-only installs should gain sessionEnd + afterAgentResponse on reinstall."""
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            cursor = home / ".cursor"
            cursor.mkdir(parents=True)
            legacy = {
                "version": 1,
                "hooks": {
                    "stop": [{"command": f"bossku sync-hook # {HOOK_MARKER}"}],
                },
            }
            (cursor / "hooks.json").write_text(json.dumps(legacy, indent=2), encoding="utf-8")
            result = install_hooks(home=home, tools=("cursor",))
            self.assertEqual(result["cursor"]["status"], "installed")
            data = json.loads((cursor / "hooks.json").read_text(encoding="utf-8"))
            self.assertEqual(len(data["hooks"]["stop"]), 1)  # not duplicated
            for event in CURSOR_EVENTS:
                self.assertIn(event, data["hooks"])
                self.assertTrue(any(HOOK_MARKER in json.dumps(e) for e in data["hooks"][event]))

    def test_codex_wrapper_and_features_hooks(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            codex = home / ".codex"
            codex.mkdir(parents=True)
            (codex / "config.toml").write_text("[model]" + chr(10) + 'name = "gpt"' + chr(10), encoding="utf-8")
            result = install_hooks(home=home, tools=("codex",))
            self.assertEqual(result["codex"]["status"], "installed")
            data = json.loads((codex / "hooks.json").read_text(encoding="utf-8"))
            for event in CODEX_EVENTS:
                self.assertIn(event, data["hooks"])
                dumped = json.dumps(data["hooks"][event])
                self.assertIn(HOOK_MARKER, dumped)
                self.assertIn("codex-sync-hook", dumped)
            cfg = (codex / "config.toml").read_text(encoding="utf-8")
            self.assertIn("hooks = true", cfg)
            self.assertIn('name = "gpt"', cfg)
            wrapper = Path(result["codex"]["wrapper"])
            self.assertTrue(wrapper.is_file())
            import shutil
            import subprocess
            if wrapper.suffix.lower() == ".ps1":
                cmd = [
                    "powershell",
                    "-NoProfile",
                    "-ExecutionPolicy",
                    "Bypass",
                    "-File",
                    str(wrapper),
                ]
            else:
                bash = shutil.which("bash") or "bash"
                cmd = [bash, str(wrapper)]
            out = subprocess.check_output(cmd, stdin=subprocess.DEVNULL, text=True)
            self.assertIn('"continue"', out)

            data["hooks"]["Stop"].insert(0, {"hooks": [{"type": "command", "command": "echo keep-me"}]})
            (codex / "hooks.json").write_text(json.dumps(data, indent=2), encoding="utf-8")
            un = uninstall_hooks(home=home, tools=("codex",))
            self.assertEqual(un["codex"]["status"], "removed")
            after = json.loads((codex / "hooks.json").read_text(encoding="utf-8"))
            self.assertIn("echo keep-me", json.dumps(after["hooks"].get("Stop", [])))
            self.assertNotIn(HOOK_MARKER, json.dumps(after.get("hooks", {})))
            self.assertFalse(wrapper.exists())

    def test_install_user_refreshes_hooks_by_default(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            (home / ".cursor").mkdir(parents=True)
            result = install_user(root=ROOT, home=home, profile="core")
            self.assertIn("hooks", result)
            self.assertEqual(result["hooks"]["cursor"]["status"], "installed")
            data = json.loads((home / ".cursor" / "hooks.json").read_text(encoding="utf-8"))
            for event in CURSOR_EVENTS:
                self.assertIn(event, data["hooks"])


class SkillTests(unittest.TestCase):
    def test_skill_audit_cli_outputs_json(self):
        stdout = io.StringIO()
        with redirect_stdout(stdout):
            result = main(["skills", "audit", "--root", str(ROOT), "--json"])
        self.assertEqual(result, 0)
        report = json.loads(stdout.getvalue())
        self.assertGreater(report["skill_count"], 0)
        self.assertEqual(report["custom_broken_relative_links"], [])

    def test_skill_find_cli_exposes_recommended_stack(self):
        stdout = io.StringIO()
        with redirect_stdout(stdout):
            result = main(
                [
                    "skills",
                    "find",
                    "fix mobile overflow and remove generic AI UI",
                    "--root",
                    str(ROOT),
                ]
            )
        self.assertEqual(result, 0)
        payload = json.loads(stdout.getvalue())
        self.assertTrue(payload["recommended_stack"])
        self.assertIn("stack_note", payload)

    def test_resolve_alias(self):
        self.assertEqual(
            resolve_skill_id("bosskuai-caveman", ROOT),
            "bosskuai-token-saver",
        )

    def test_find_cofounder_task(self):
        sid, score = find_skill("cofounder mode what should we build next", ROOT)
        self.assertTrue(score >= 0)

    def test_find_loop_skills_from_description(self):
        sid, score = find_skill("ci failure", ROOT)
        self.assertEqual(sid, "ci-triage")
        self.assertGreater(score, 0)
        sid, _ = find_skill("open pull requests", ROOT)
        self.assertEqual(sid, "pr-review-triage")
        sid, _ = find_skill("release notes", ROOT)
        self.assertEqual(sid, "draft-release-notes")
        sid, score = find_skill("agent loop triage", ROOT)
        self.assertEqual(sid, "loop-triage")
        self.assertGreater(score, 0)
        sid, score = find_skill("address review comment minimal patch", ROOT)
        self.assertEqual(sid, "minimal-fix")
        self.assertGreater(score, 0)


class ValidateTests(unittest.TestCase):
    def test_validate_repo_passes(self):
        errors = validate_repo(ROOT)
        self.assertEqual(errors, [], msg="\n".join(errors))

    def test_package_version_matches_manifests(self):
        version = package_version(ROOT)
        self.assertEqual(version, "2.1.0")
        self.assertEqual(bossku.__version__, version)
        errors = validate_plugin_manifests(ROOT)
        self.assertEqual(errors, [], msg="\n".join(errors))


class DoctorTests(unittest.TestCase):
    def _write_fake_install(self, home: Path) -> None:
        cfg_dir = user_config_dir(home)
        cfg_dir.mkdir(parents=True, exist_ok=True)
        (cfg_dir / "config.json").write_text(
            json.dumps({"installed_from": str(ROOT), "profile": "core"}),
            encoding="utf-8",
        )

    def test_doctor_fails_when_skill_dirs_empty(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            self._write_fake_install(home)
            (home / ".agents" / "skills").mkdir(parents=True)
            (home / ".claude" / "skills").mkdir(parents=True)
            issues = gather_doctor_issues(ROOT, home)
            self.assertTrue(any("no managed skills" in i for i in issues))

    def test_doctor_ok_after_install(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            install_user(root=ROOT, home=home, profile="core")
            issues = gather_doctor_issues(ROOT, home)
            self.assertEqual(issues, [], msg="\n".join(issues))

    def test_doctor_project_adapter_without_init(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            project = Path(tmp) / "bare"
            project.mkdir()
            (project / "AGENTS.md").write_text("# no block\n", encoding="utf-8")
            install_user(root=ROOT, home=home, profile="core")
            issues = gather_doctor_issues(ROOT, home, project=project)
            self.assertTrue(any("managed block" in i or "CLAUDE.md" in i for i in issues))

    def test_doctor_project_adapter_after_init(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            project = Path(tmp) / "ready"
            install_user(root=ROOT, home=home, profile="core")
            init_project(project, root=ROOT)
            issues = gather_doctor_issues(ROOT, home, project=project)
            self.assertEqual(issues, [], msg="\n".join(issues))
            stdout = io.StringIO()
            with redirect_stdout(stdout):
                self.assertEqual(_doctor(ROOT, home, project), 0)
            self.assertIn(f"project instructions: ready at {project.resolve()}", stdout.getvalue())


if __name__ == "__main__":
    unittest.main()
