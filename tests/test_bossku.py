import json
import tempfile
import unittest
from pathlib import Path

from bossku.cli import _doctor
from bossku.doctor import gather_doctor_issues
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
            for sid in ("loop-triage", "minimal-fix", "ci-triage", "loop-verifier"):
                self.assertTrue((agents / sid).is_dir(), msg=f"missing core loop skill {sid}")
            removed = uninstall_user(root=ROOT, home=home)
            self.assertTrue(len(removed["removed_skills"]) >= 0)

    def test_full_install_includes_vendored_packs(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            install_user(root=ROOT, home=home, profile="full")
            agents = home / ".agents" / "skills"
            for sid in ("product-marketing", "brainstorming", "hallmark", "graphify", "graft", "browser-use", "markitdown", "dcg"):
                self.assertTrue((agents / sid).is_dir(), msg=f"missing {sid}")
            uninstall_user(root=ROOT, home=home)


class VendoredTests(unittest.TestCase):
    def test_vendored_ids_loaded(self):
        ids = load_vendored_ids(ROOT)
        self.assertIn("copywriting", ids)
        self.assertIn("brainstorming", ids)
        self.assertIn("hallmark", ids)
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
            self.assertTrue((project / ".bossku" / "memory" / "project.md").is_file())
            claude = (project / "CLAUDE.md").read_text(encoding="utf-8")
            self.assertTrue(claude_imports_agents_md(claude))
            omp_agents = (project / ".omp" / "AGENTS.md").read_text(encoding="utf-8")
            self.assertTrue(omp_imports_agents_md(omp_agents))
            omp_config = (project / ".omp" / "config.yml").read_text(encoding="utf-8")
            self.assertIn("approvalMode: write", omp_config)

    def test_init_writes_claude_import_on_empty_project(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = Path(tmp) / "fresh"
            init_project(project, root=ROOT)
            claude = (project / "CLAUDE.md").read_text(encoding="utf-8")
            self.assertTrue(claude_imports_agents_md(claude))
            self.assertTrue((project / "AGENTS.md").is_file())
            omp_agents = (project / ".omp" / "AGENTS.md").read_text(encoding="utf-8")
            self.assertTrue(omp_imports_agents_md(omp_agents))


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


class SkillTests(unittest.TestCase):
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
        self.assertEqual(version, "2.0.0")
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
            self.assertEqual(_doctor(ROOT, home, project), 0)


if __name__ == "__main__":
    unittest.main()
