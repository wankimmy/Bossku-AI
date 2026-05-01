import importlib.util
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def load_script(name):
    script_path = ROOT / "scripts" / f"{name}.py"
    spec = importlib.util.spec_from_file_location(name, script_path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ScriptTests(unittest.TestCase):
    def test_route_prompt_prefers_exact_skill_match(self):
        eval_workspace = load_script("eval_workspace")
        index = {
            "routing": {
                "core_skill_ids": [],
                "manual_only_skill_ids": [],
                "default_skill_id": "cofounder",
                "no_specialist_min_score": 1.2,
                "ambiguity_margin": 1.2,
            },
            "skills": [
                {
                    "id": "bosskuai-token-saver",
                    "triggers": ["shorter output"],
                    "keywords": ["tokens", "compressed"],
                }
            ],
        }

        result = eval_workspace.route_prompt("Use bosskuai-token-saver for shorter output", index)

        self.assertEqual(result["predicted_skill"], "bosskuai-token-saver")
        self.assertEqual(result["candidates"][0]["skill_id"], "bosskuai-token-saver")

    def test_route_prompt_falls_back_when_non_core_result_is_ambiguous(self):
        eval_workspace = load_script("eval_workspace")
        index = {
            "routing": {
                "core_skill_ids": [],
                "manual_only_skill_ids": [],
                "default_skill_id": "cofounder",
                "no_specialist_min_score": 1.2,
                "ambiguity_margin": 1.2,
            },
            "skills": [
                {"id": "alpha-skill", "triggers": ["deploy"], "keywords": ["server"]},
                {"id": "beta-skill", "triggers": ["deploy"], "keywords": ["server"]},
            ],
        }

        result = eval_workspace.route_prompt("deploy server", index)

        self.assertEqual(result["predicted_skill"], "cofounder")
        self.assertEqual(result["fallback"], "ambiguous_non_core")

    def test_learning_log_parser_extracts_status_and_review_date(self):
        rotate_learning_log = load_script("rotate_learning_log")
        content = """# Learning Log

### 2026-01-15 Routing cleanup

**Status:** applied
**Last reviewed:** 2026-02-01
**Decision:** Keep routing lightweight.
"""

        entries = rotate_learning_log.parse_entries(content)

        self.assertEqual(len(entries), 1)
        self.assertEqual(entries[0]["status"], "applied")
        self.assertEqual(entries[0]["last_reviewed"], "2026-02-01")

    def test_learning_log_summary_uses_decision_line(self):
        rotate_learning_log = load_script("rotate_learning_log")
        entry = {
            "title": "### 2026-01-15 Routing cleanup",
            "status": "applied",
            "last_reviewed": "2026-02-01",
            "body": "**Decision:** Keep routing lightweight.",
        }

        summary = rotate_learning_log.summarise(entry)

        self.assertIn("Routing cleanup", summary)
        self.assertIn("Keep routing lightweight.", summary)


if __name__ == "__main__":
    unittest.main()
