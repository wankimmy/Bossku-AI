import json
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path

from bossku.index import (
    build_index,
    index_is_stale,
    index_path,
    load_index,
    singular,
    tokenize,
    variants,
    write_index,
)
from bossku.skills import (
    _parse_frontmatter,
    find_skill,
    overdue_packs,
    pack_stocktake,
    rank_skills,
    validate_skills,
)

ROOT = Path(__file__).resolve().parents[1]

# Representative routing contract. Each case lists every skill that is a defensible
# answer, so genuine overlaps (tdd-loop vs test-driven-development) are not scored
# as failures. Floors are set below measured accuracy to catch regressions, not drift.
ROUTING_CASES = [
    ("the login endpoint returns 500 intermittently", {"bosskuai-diagnose-loop", "systematic-debugging"}),
    ("write tests before implementing the payment flow", {"bosskuai-tdd-loop", "test-driven-development"}),
    ("design the database schema for multi-tenant orders", {"bosskuai-database-engineering"}),
    ("our AWS bill doubled last month", {"bosskuai-cost-optimization"}),
    ("set up docker compose for this project", {"bosskuai-docker"}),
    ("add scroll animations with GSAP", {"bosskuai-gsap-animation"}),
    ("the CI pipeline is failing on main", {"ci-triage"}),
    ("check for prompt injection in our agent tools", {"bosskuai-prompt-injection-defense"}),
    ("what model should I use for this task", {"bosskuai-ai-model-selection"}),
    ("deploy to a VPS with docker", {"bosskuai-vps-docker-deployment"}),
    ("run an A/B test on the pricing page", {"ab-testing"}),
    ("design a REST API for the orders service", {"bosskuai-api-design"}),
    ("we had an outage last night, write the postmortem", {"bosskuai-incident-response"}),
    ("make the app faster, it's slow on load", {"bosskuai-performance-profiling"}),
    ("secure our Laravel app", {"bosskuai-laravel-security"}),
    ("brainstorm ideas for a new feature", {"brainstorming"}),
    ("translate the app into Malay and Chinese", {"bosskuai-i18n-l10n"}),
    ("is this PDPA compliant", {"bosskuai-malaysia-pdpa-privacy"}),
    ("convert this pdf into markdown", {"markitdown"}),
    ("forecast our runway and burn rate", {"bosskuai-financial-modeling"}),
    ("stop the agent from running rm -rf", {"dcg"}),
    ("our users are churning, what do we do", {"churn-prevention"}),
    ("build a three.js hero with a rotating model", {"bosskuai-3d-web-development"}),
    ("git worktree for this feature branch", {"using-git-worktrees"}),
    ("write release notes for v2", {"draft-release-notes"}),
    ("review this animation for craft issues", {"review-animations"}),
    ("what should I use for toasts", {"pick-ui-library"}),
    ("scrolltrigger pinned section with gsap", {"bosskuai-gsap-animation"}),
]


class TokenizerTests(unittest.TestCase):
    def test_slash_separated_terms_do_not_glue(self):
        # `Three.js/React` must not become one token, or a `three.js` query misses it.
        tokens = tokenize("Three.js/React Three Fiber")
        self.assertIn("three.js", tokens)
        self.assertIn("react", tokens)

    def test_dotted_token_also_yields_parts(self):
        self.assertIn("three", tokenize("three.js"))

    def test_singular_keeps_short_and_ss_words(self):
        self.assertEqual(singular("aws"), "aws")
        self.assertEqual(singular("css"), "css")
        self.assertEqual(singular("emails"), "email")

    def test_variants_reach_verb_forms(self):
        self.assertIn("churn", variants("churning"))
        self.assertIn("translate", variants("translation"))


class FrontmatterTests(unittest.TestCase):
    def test_block_scalar_with_chomping_indicator(self):
        # `>-` previously parsed as the literal string ">-", wiping the description.
        text = "---\nname: x\ndescription: >-\n  hello\n  world\n---\nbody\n"
        self.assertEqual(_parse_frontmatter(text)["description"], "hello world")

    def test_triple_dash_inside_value_does_not_close_frontmatter(self):
        text = '---\nname: x\ndescription: "a --- b"\nlicense: MIT\n---\nbody\n'
        front = _parse_frontmatter(text)
        self.assertEqual(front["license"], "MIT")

    def test_every_shipped_skill_has_a_real_description(self):
        index = load_index(ROOT) or build_index(ROOT)
        for sid, entry in index["skills"].items():
            with self.subTest(skill=sid):
                self.assertGreater(len(entry["description"]), 20, f"{sid} description did not parse")


class IndexTests(unittest.TestCase):
    def test_index_committed_and_fresh(self):
        self.assertTrue(index_path(ROOT).is_file(), "run `bossku skills index`")
        self.assertFalse(index_is_stale(ROOT), "skill-index.json is stale; run `bossku skills index`")

    def test_index_covers_every_skill_with_a_known_role(self):
        index = load_index(ROOT)
        self.assertEqual(index["count"], len(index["skills"]))
        for sid, entry in index["skills"].items():
            with self.subTest(skill=sid):
                self.assertIn(entry["model_role"], ("planner", "coder", "reviewer", "researcher"))
                self.assertTrue(entry["triggers"])

    def test_write_index_is_deterministic(self):
        first = json.dumps(build_index(ROOT), sort_keys=True)
        second = json.dumps(build_index(ROOT), sort_keys=True)
        self.assertEqual(first, second)


class RoutingTests(unittest.TestCase):
    def test_top1_accuracy_floor(self):
        hits = [q for q, expected in ROUTING_CASES if find_skill(q, ROOT)[0] in expected]
        accuracy = len(hits) / len(ROUTING_CASES)
        misses = [q for q, expected in ROUTING_CASES if find_skill(q, ROOT)[0] not in expected]
        self.assertGreaterEqual(accuracy, 0.80, f"top-1 routing regressed; missed: {misses}")

    def test_top3_recall_floor(self):
        hits = 0
        for q, expected in ROUTING_CASES:
            if {sid for sid, _ in rank_skills(q, ROOT, limit=3)} & expected:
                hits += 1
        self.assertGreaterEqual(hits / len(ROUTING_CASES), 0.88, "top-3 recall regressed")

    def test_incidental_word_does_not_hijack_routing(self):
        # "design tokens" must not route to token-saver just because it says "token".
        top = [sid for sid, _ in rank_skills("add dark mode to the design tokens", ROOT, limit=3)]
        self.assertIn("bosskuai-design-systems", top)

    def test_unmatched_query_falls_back_without_crashing(self):
        sid, score = find_skill("zzzz qqqq vvvv", ROOT)
        self.assertTrue(sid)
        self.assertEqual(score, 0.0)


class StocktakeTests(unittest.TestCase):
    def test_every_vendored_pack_has_provenance(self):
        for row in pack_stocktake(ROOT):
            with self.subTest(pack=row["pack"]):
                self.assertTrue(row["upstream"], f"{row['pack']} missing upstream")
                self.assertTrue(row["last_synced"], f"{row['pack']} missing last_synced")

    def test_nothing_overdue_today(self):
        self.assertEqual(overdue_packs(ROOT), [])

    def test_packs_go_overdue_past_the_window(self):
        # Time-travel rather than trust the happy path: every pack must age out.
        rows = pack_stocktake(ROOT)
        window = rows[0]["review_days"]
        newest = max(date.fromisoformat(r["last_synced"]) for r in rows)
        future = newest + timedelta(days=window + 1)
        self.assertEqual(len(overdue_packs(ROOT, today=future)), len(rows))

    def test_missing_sync_date_counts_as_overdue(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            (base / "skills").mkdir()
            (base / "skills" / "vendored.json").write_text(
                json.dumps({"packs": {"ghost": ["a"]}, "skills": {}, "provenance": {}}),
                encoding="utf-8",
            )
            self.assertEqual(overdue_packs(base), ["ghost"])


class ValidatorTests(unittest.TestCase):
    def _skill(self, base: Path, name: str, frontmatter: str) -> None:
        d = base / "skills" / name
        d.mkdir(parents=True)
        (d / "SKILL.md").write_text(f"---\n{frontmatter}\n---\n\n# {name}\n", encoding="utf-8")

    def _repo(self, tmp: str) -> Path:
        base = Path(tmp)
        (base / "skills").mkdir()
        (base / "skills" / "aliases.json").write_text('{"aliases": {}}', encoding="utf-8")
        (base / "skills" / "vendored.json").write_text('{"packs": {}, "skills": {}}', encoding="utf-8")
        return base

    def test_flags_unknown_key_and_missing_description(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = self._repo(tmp)
            self._skill(base, "bad-one", "name: bad-one\ndescription: " + "x" * 60 + "\ntools: Read")
            self._skill(base, "bad-two", "name: bad-two")
            errors = " ".join(validate_skills(base))
            self.assertIn("unknown frontmatter key", errors)
            self.assertIn("missing description", errors)

    def test_flags_oversized_description(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = self._repo(tmp)
            self._skill(base, "huge", f'name: huge\ndescription: "{"x" * 1400}"')
            self.assertIn("too long", " ".join(validate_skills(base)))

    def test_accepts_a_well_formed_skill(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = self._repo(tmp)
            self._skill(base, "good", "name: good\ndescription: " + "a real description " * 4)
            self.assertEqual(validate_skills(base), [])


if __name__ == "__main__":
    unittest.main()
