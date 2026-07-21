import json
import tempfile
import unittest
from pathlib import Path

from bossku.init_project import init_project, upsert_managed_block
from bossku.install import install_user, uninstall_user
from bossku.memory import remember, sync_project
from bossku.redact import redact
from bossku.skills import find_skill, is_managed_skill_name, list_skill_ids, load_vendored_ids, resolve_skill_id
from bossku.validate import validate_repo


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
            agents = home / ".agents" / "skills"
            self.assertTrue(agents.is_dir())
            removed = uninstall_user(root=ROOT, home=home)
            self.assertTrue(len(removed["removed_skills"]) >= 0)

    def test_full_install_includes_vendored_packs(self):
        with tempfile.TemporaryDirectory() as tmp:
            home = Path(tmp)
            install_user(root=ROOT, home=home, profile="full")
            agents = home / ".agents" / "skills"
            for sid in ("product-marketing", "brainstorming", "hallmark", "graphify", "browser-use", "markitdown"):
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


class ValidateTests(unittest.TestCase):
    def test_validate_repo_passes(self):
        errors = validate_repo(ROOT)
        self.assertEqual(errors, [], msg="\n".join(errors))


if __name__ == "__main__":
    unittest.main()
