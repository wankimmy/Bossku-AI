"""Build and load the skill routing index (skills/skill-index.json).

The index carries the trigger/keyword data the router scores against. It lives
on disk rather than in SKILL.md frontmatter so routing quality costs zero
always-loaded context: hosts only ever read `name` + `description`.
"""

from __future__ import annotations

import hashlib
import json
import math
import re
from pathlib import Path

from bossku.paths import repo_root
from bossku.skills import (
    list_skill_ids,
    load_aliases,
    load_vendored,
    parse_skill_md,
    skills_dir,
)

INDEX_VERSION = "2.1.0"
MODEL_ROLES = ("planner", "coder", "reviewer", "researcher")

STOPWORDS = frozenset("""
a about above after again against all also am an and any are as at be because been
before being below between both but by can cannot could did do does doing down during
each few for from further had has have having he her here hers him his how i if in
into is it its itself just me more most my no nor not of off on once only or other
our out over own same she should so some such than that the their them then there
these they this those through to too under until up use used uses using very was we
were what when where which while who whom why will with would you your
skill skills user users need needs want wants make makes making help helps get gets
also e.g i.e etc via across whether including include includes something anything
""".split())

# Curated triggers for requests whose vocabulary does not appear in the skill id
# or description. Everything else is derived automatically.
CURATED_TRIGGERS: dict[str, list[str]] = {
    "bosskuai-diagnose-loop": [
        "bug", "broken", "failing", "crash", "throws", "exception", "stack trace",
        "returns 500", "error", "intermittent", "flaky", "regression", "not working",
        "why is this", "reproduce",
    ],
    "bosskuai-performance-profiling": [
        "slow", "faster", "speed up", "latency", "sluggish", "takes too long",
        "profiling", "bottleneck", "memory leak", "high cpu",
    ],
    "bosskuai-cost-optimization": [
        "bill", "aws bill", "cloud bill", "spend", "too expensive", "burn",
        "cost control", "token budget", "unit economics",
    ],
    "bosskuai-incident-response": [
        "outage", "postmortem", "post-mortem", "incident", "sev1", "sev2",
        "downtime", "went down", "on-call", "paging",
    ],
    "bosskuai-ai-model-selection": [
        "which model", "what model", "model choice", "pick a model", "model routing",
    ],
    "bosskuai-project-understanding": [
        "how does this work", "explain the codebase", "understand this repo",
        "what does this project do", "onboard me", "unfamiliar codebase",
        "how this codebase works", "what is this project", "get up to speed",
    ],
    "bosskuai-codebase-analysis": [
        "trace", "call chain", "execution path", "side effects", "where is this called",
    ],
    "bosskuai-code-revamp": [
        "refactor", "legacy", "modernize", "clean up", "tech debt", "restructure",
    ],
    "bosskuai-rigorous-code-review": [
        "review this code", "code review", "review my changes", "review the diff",
        "review this pull request", "critique this implementation",
    ],
    "bosskuai-tdd-loop": [
        "write tests first", "test first", "tdd", "red green refactor",
        "write tests before",
    ],
    "bosskuai-database-engineering": [
        "database schema", "sql", "index", "query plan", "migration", "postgres",
        "mysql", "mariadb", "slow query", "normalize",
    ],
    "bosskuai-api-design": [
        "rest api", "graphql", "endpoint design", "api contract", "versioning",
        "pagination", "idempotency",
    ],
    "bosskuai-vps-docker-deployment": [
        "vps", "deploy to server", "ship to production", "provision", "nginx",
    ],
    "bosskuai-docker": ["dockerfile", "docker compose", "containerize"],
    "bosskuai-gsap-animation": [
        "gsap", "scrolltrigger", "timeline animation", "scroll animation",
        "pinned section", "scroll storytelling", "gsap timeline",
    ],
    "bosskuai-throwaway-prototype": [
        "throwaway spike", "throwaway prototype", "prove it works",
        "one-question prototype", "spike to learn",
    ],
    "bosskuai-rapid-prototype": [
        "mvp scaffold", "demo build", "hackathon prototype", "rapid prototype",
        "poc scaffold",
    ],
    "animate": [
        "add a transition", "animate this component", "build an animation",
        "make a transition", "component feel alive",
    ],
    "review-animations": [
        "review this animation", "motion review", "animation code review",
        "critique this motion", "review the animations",
    ],
    "improve-animations": [
        "audit the animations", "improve the motion", "animation roadmap",
        "improve the animations", "motion audit",
    ],
    "find-animation-opportunities": [
        "what could be animated", "make this feel alive", "animation opportunities",
        "where should we animate",
    ],
    "animation-vocabulary": [
        "what's it called when", "name for this effect", "what is this animation called",
        "animation vocabulary",
    ],
    "pick-ui-library": [
        "which library for", "what should I use for toasts", "drag and drop library",
        "pick a ui library", "which package for charts",
    ],
    "prototype": [
        "variants behind a picker", "try a few versions", "ui variant picker",
        "multiple versions of this ui", "prototype three versions",
    ],
    "apple-design": [
        "ios feel", "spring interaction", "sheet drag gesture",
        "apple style ui", "fluid interface", "rubber band scroll",
    ],
    "emil-design-eng": [
        "make this feel right", "ui polish", "design engineering",
        "emil kowalski", "invisible details",
    ],
    "bosskuai-laravel-security": [
        "secure laravel", "laravel security", "mass assignment", "csrf", "policy gate",
        "secure", "harden", "vulnerability",
    ],
    "bosskuai-prompt-injection-defense": [
        "prompt injection", "jailbreak", "tool abuse", "memory poisoning",
        "untrusted input to llm",
    ],
    "bosskuai-tenant-isolation-security": [
        "multi-tenant", "multitenant", "tenant isolation", "cross-tenant", "row level security",
    ],
    "bosskuai-observability-sre": [
        "logging", "metrics", "tracing", "alerting", "slo", "dashboards", "monitoring",
    ],
    "bosskuai-context-limit-continuation": [
        "running out of context", "context limit", "token limit", "compact", "out of tokens",
    ],
    "bosskuai-handoff": ["handoff", "hand off", "pass to another agent", "continue in a new session"],
    "ci-triage": ["ci failing", "ci pipeline", "build failing", "github actions failing", "red build"],
    "pr-review-triage": [
        "pr comments", "review comments", "address feedback", "unresolved comments",
        "pull request", "pull requests", "open prs", "review queue",
    ],
    "issue-triage": ["triage issues", "backlog of issues", "label issues"],
    "dependency-triage": ["dependabot", "bump dependencies", "outdated packages", "cve in dependency"],
    "brainstorming": ["brainstorm", "ideas for", "explore options", "come up with"],
    "systematic-debugging": ["debug", "root cause", "narrow down the cause"],
    "test-driven-development": ["tdd", "write a failing test"],
    "writing-plans": ["write a plan", "implementation plan", "spec into a plan"],
    "executing-plans": ["execute the plan", "work through the plan"],
    "taste-skill": [
        "landing page", "does not look ai", "doesn't look ai generated", "ai slop",
        "make it look good", "design taste", "beautiful ui", "marketing site",
    ],
    "marketing-plan": [
        "go to market", "gtm plan", "growth plan", "marketing roadmap", "aarrr",
        "12 month plan", "90 day plan",
    ],
    "seo-audit": ["seo audit", "audit our seo", "technical seo", "crawl issues"],
    "ab-testing": ["ab test", "a/b test", "split test", "which version wins", "statistical significance"],
    "analytics": ["event tracking", "ga4", "google analytics", "tracking plan", "utm", "gtm container"],
    "cold-email": ["cold email", "outreach email", "outbound email", "email prospects"],
    "onboarding": ["user onboarding", "activation rate", "first run experience", "onboarding conversion"],
    "pricing": ["pricing tiers", "how much should i charge", "packaging", "freemium"],
    "churn-prevention": [
        "churn", "churning", "cancellations", "win back", "retention", "downgrade",
        "users are leaving",
    ],
    "bosskuai-i18n-l10n": [
        "translate", "translation", "localization", "localisation", "locale",
        "multilingual", "language support", "rtl", "malay", "chinese",
    ],
    "markitdown": [
        "pdf", "docx", "xlsx", "pptx", "convert to markdown", "office document",
        "extract text from",
    ],
    "bosskuai-financial-modeling": [
        "runway", "burn rate", "forecast", "projections", "arr", "mrr", "cash flow",
        "financial model", "revenue model",
    ],
    "dcg": [
        "rm -rf", "destructive command", "dangerous command", "shell safety",
        "guard rails for shell", "block dangerous", "force push protection",
    ],
    "bosskuai-redis-caching-queues": ["redis", "cache", "queue", "worker", "pub sub", "rate limit"],
    "bosskuai-investor-prep": ["investor update", "pitch deck", "fundraising", "due diligence", "data room"],
    "using-git-worktrees": ["worktree", "git worktree", "parallel branches"],
    "bosskuai-mongodb": ["mongodb", "mongo", "aggregation pipeline", "document store"],
    "bosskuai-nuxt-development": ["nuxt", "nitro", "vue app"],
    "bosskuai-browser-automation": ["playwright", "puppeteer", "headless browser", "e2e test"],
    "bosskuai-malaysia-pdpa-privacy": ["pdpa", "personal data protection", "malaysia privacy"],
    "bosskuai-legal-compliance": ["gdpr", "terms of service", "privacy policy", "compliance"],
    "draft-release-notes": ["release notes", "changelog for release", "what shipped"],
    "bosskuai-content-calendar": ["content calendar", "posting schedule", "editorial calendar"],
}

# Explicit role assignments; the rest fall back to keyword heuristics.
CURATED_ROLES: dict[str, str] = {
    "bosskuai-planning-execution": "planner",
    "bosskuai-software-architecture": "planner",
    "bosskuai-product-strategy": "planner",
    "bosskuai-council": "planner",
    "bosskuai-rigorous-code-review": "reviewer",
    "bosskuai-deep-research": "researcher",
    "bosskuai-market-analysis": "researcher",
}

_ROLE_HINTS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("reviewer", (
        "review", "audit", "verification", "verify", "security", "risk", "compliance",
        "quality", "lint", "check", "inspect", "assurance", "guard", "isolation",
    )),
    ("planner", (
        "plan", "planning", "architecture", "strategy", "roadmap", "design system",
        "decision", "tradeoff", "scoping", "orchestration", "prioritis", "prioritiz",
    )),
    ("researcher", (
        "research", "analysis", "analytics", "discovery", "intelligence", "market",
        "competitor", "seo", "marketing", "content", "copy", "sales", "customer",
        "growth", "brand", "social", "campaign", "outreach", "pricing", "investor",
        "finance", "financial", "legal",
    )),
)

_WORD = re.compile(r"[a-z0-9][a-z0-9+.#-]*")
_QUOTED = re.compile(r"['‘’\"“”]([^'‘’\"“”]{3,60}?)['‘’\"“”]")


def index_path(root: Path | None = None) -> Path:
    return repo_root(root) / "skills" / "skill-index.json"


def singular(token: str) -> str:
    """Cheap plural fold so `emails` matches `email`.

    Alphabetic tokens only, and long enough to leave `aws`/`css` alone - folding
    `three.js` to `three.j` would break every version-suffixed library name.
    """
    if (
        token.isalpha()
        and len(token) > 4
        and token.endswith("s")
        and not token.endswith(("ss", "us", "is"))
    ):
        return token[:-1]
    return token


def tokenize(text: str) -> list[str]:
    """Split on `/` so `Three.js/React` is two terms, and also emit dotted sub-parts
    so a query for `three.js` reaches a description that only says `Three.js`."""
    out: list[str] = []
    for raw in _WORD.findall(text.lower().replace("/", " ")):
        token = raw.strip(".-")
        parts = [token, *token.split(".")] if "." in token else [token]
        for part in parts:
            # Two-letter terms carry real signal here (ci, qa, ux, pr, 3d, ai);
            # the stopword list handles the noisy ones.
            if len(part) >= 2 and part not in STOPWORDS:
                out.append(singular(part))
    return out


def variants(token: str) -> set[str]:
    """Query-side morphology so `churning` reaches `churn` and `translation` reaches `translate`.

    Applied only when scoring a query - the index stays on plain singular forms, so a
    bad fold can add a spurious match but can never corrupt stored keywords.
    """
    out = {token, singular(token)}
    for suffix in ("ing", "ed"):
        if token.endswith(suffix) and len(token) - len(suffix) >= 3:
            stem = token[: -len(suffix)]
            out.add(stem)
            if len(stem) > 3 and stem[-1] == stem[-2]:
                out.add(stem[:-1])  # running -> runn -> run
            out.add(stem + "e")  # translating -> translate
    for suffix in ("ation", "ion", "ments", "ment"):
        if token.endswith(suffix) and len(token) - len(suffix) >= 4:
            stem = token[: -len(suffix)]
            out.add(stem)
            out.add(stem + "e")  # translation -> translate
    return {v for v in out if len(v) >= 2}


def _id_tokens(skill_id: str) -> list[str]:
    return [t for t in skill_id.replace("bosskuai-", "").split("-") if len(t) > 1]


def _clean(phrases: list[str], limit: int) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for p in phrases:
        p = re.sub(r"\s+", " ", p).strip()
        if p and p not in seen:
            seen.add(p)
            out.append(p)
    return out[:limit]


def _derive_triggers(skill_id: str, description: str) -> tuple[list[str], list[str]]:
    """Return (triggers, phrases).

    `triggers` are high-confidence: the skill's own id phrase plus hand-curated wording.
    `phrases` are salvaged from the description (quoted examples, `Triggers:` clauses)
    and score lower, because a keyword-stuffed description yields noisy matches.
    """
    ident = skill_id.replace("bosskuai-", "").replace("-", " ")
    triggers = [ident, *CURATED_TRIGGERS.get(skill_id, [])]

    derived: list[str] = []
    for m in _QUOTED.finditer(description):
        phrase = m.group(1).strip().strip(",.;:").lower()
        if 3 <= len(phrase) <= 60 and not phrase.startswith("http"):
            derived.append(phrase)

    for m in re.finditer(r"(?:Triggers?|Use when|Use this when)\s*:?\s*([^.]{3,200})", description, re.I):
        for part in re.split(r",| or | and |/", m.group(1)):
            phrase = part.strip().strip("'\"").lower()
            if 4 <= len(phrase) <= 50 and len(tokenize(phrase)) <= 5:
                derived.append(phrase)

    curated = set(triggers)
    return _clean(triggers, 40), _clean([p for p in derived if p not in curated], 40)


def _derive_role(skill_id: str, description: str) -> str:
    if skill_id in CURATED_ROLES:
        return CURATED_ROLES[skill_id]
    haystack = f"{skill_id} {description}".lower()
    for role, hints in _ROLE_HINTS:
        if any(h in haystack for h in hints):
            return role
    return "coder"


def _headings(text: str) -> list[str]:
    return re.findall(r"^#{2,3}\s+(.{3,60})$", text, re.M)[:25]


def skills_fingerprint(root: Path | None = None) -> str:
    """Hash of every skill's id + frontmatter, so staleness is detectable."""
    base = skills_dir(root)
    h = hashlib.sha256()
    for sid in list_skill_ids(root):
        meta = parse_skill_md(base / sid / "SKILL.md")
        h.update(sid.encode())
        h.update(b"\0")
        h.update(meta.name.encode())
        h.update(b"\0")
        h.update(meta.description.encode())
        h.update(b"\n")
    return h.hexdigest()[:16]


def build_index(root: Path | None = None) -> dict:
    r = repo_root(root)
    base = skills_dir(r)
    vendored = load_vendored(r)
    entries: dict[str, dict] = {}

    for sid in list_skill_ids(r):
        path = base / sid / "SKILL.md"
        meta = parse_skill_md(path)
        text = path.read_text(encoding="utf-8")
        description = meta.description
        triggers, phrases = _derive_triggers(sid, description)

        kw: list[str] = []
        seen: set[str] = set()
        curated_tokens = tokenize(" ".join(CURATED_TRIGGERS.get(sid, [])))
        for token in _id_tokens(sid) + curated_tokens + tokenize(description) + tokenize(" ".join(_headings(text))):
            if token not in seen:
                seen.add(token)
                kw.append(token)

        entries[sid] = {
            "path": f"skills/{sid}/SKILL.md",
            "name": meta.name,
            "description": description,
            "triggers": triggers,
            "phrases": phrases,
            "keywords": kw[:60],
            "model_role": _derive_role(sid, description),
            "pack": vendored.get(sid, "bossku"),
        }

    return {
        "version": INDEX_VERSION,
        "fingerprint": skills_fingerprint(r),
        "count": len(entries),
        "aliases": load_aliases(r),
        "skills": entries,
    }


def write_index(root: Path | None = None) -> Path:
    dest = index_path(root)
    dest.write_text(
        json.dumps(build_index(root), indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    return dest


def load_index(root: Path | None = None) -> dict | None:
    path = index_path(root)
    if not path.is_file():
        return None
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return None


def index_is_stale(root: Path | None = None) -> bool:
    data = load_index(root)
    if data is None:
        return True
    return data.get("fingerprint") != skills_fingerprint(root)


def compute_idf(entries: dict[str, dict]) -> dict[str, float]:
    """Inverse document frequency over keywords, so shared jargon stops dominating."""
    n = max(len(entries), 1)
    df: dict[str, int] = {}
    for entry in entries.values():
        for token in set(entry.get("keywords", [])):
            df[token] = df.get(token, 0) + 1
    return {token: math.log(1 + n / (1 + count)) for token, count in df.items()}
