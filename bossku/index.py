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
    _parse_frontmatter,
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
        "bug", "broken", "failing", "crash", "throws", "exception", "stack trace", "returns 500",
        "error", "intermittent", "flaky", "regression", "not working", "why is this", "reproduce",
        "is broken", "stopped working", "debug this", "test is failing", "tests are failing",
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
        "opus or sonnet", "sonnet or haiku", "model to use", "cheaper model",
        "effort level", "model pricing", "context window",
    ],
    "bosskuai-project-understanding": [
        "how does this work", "explain the codebase", "understand this repo",
        "what does this project do", "onboard me", "unfamiliar codebase",
        "how this codebase works", "what is this project", "get up to speed",
    ],
    "bosskuai-codebase-analysis": [
        "trace", "call chain", "execution path", "side effects", "where is this called",
    ],
    "graft": [
        "graft", "graft ask", "graft grep", "graft callers", "graft map",
        "where does this live", "where is the code for", "who calls this",
        "call sites", "blast radius", "what breaks if i change", "before renaming",
        "context graph", "repo map", "file line spans",
    ],
    "bosskuai-code-revamp": [
        "refactor", "legacy", "modernize", "clean up", "tech debt", "restructure",
    ],
    "bosskuai-rigorous-code-review": [
        "review this code", "code review", "review my changes", "review the diff",
        "review this pull request", "critique this implementation", "pr review", "review this pr",
    ],
    "bosskuai-tdd-loop": [
        "write tests first", "test first", "tdd", "red green refactor", "write tests before",
        "use tdd", "with tdd", "using tdd", "write a failing test",
    ],
    "bosskuai-database-engineering": [
        "database schema", "sql", "index", "query plan", "migration", "postgres",
        "mysql", "mariadb", "slow query", "normalize",
    ],
    "bosskuai-api-design": [
        "rest api", "graphql", "endpoint design", "api contract", "versioning",
        "pagination", "idempotency",
    ],
    "bosskuai-docker": ["dockerfile", "docker compose", "containerize", "dockerize", "dockerise"],
    "bosskuai-gsap-animation": [
        "gsap", "scrolltrigger", "timeline animation", "scroll animation", "pinned section",
        "scroll storytelling", "gsap timeline", "scrollsmoother", "splittext", "morphsvg", "drawsvg",
        "svg morph", "morph svg", "svg shapes", "pin a section", "animate on scroll",
        "horizontal scroll section", "parallax scrolling", "parallax effect", "parallax hero",
        "text reveal", "scroll-driven animation", "animation-timeline",
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
        "add a transition", "animate this component", "build an animation", "make a transition",
        "component feel alive", "entrance animation", "exit animation", "hover animation",
        "hover effect", "micro-interaction", "micro-interactions", "staggered list",
        "stagger animation", "framer motion", "animatepresence", "layout animation", "page transition",
        "page transitions", "route transition", "view transition", "view transitions",
        "micro interactions",
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
        "harden laravel", "laravel hardening", "laravel production config",
    ],
    "bosskuai-context-limit-continuation": [
        "running out of context", "context limit", "token limit", "compact", "out of tokens",
    ],
    "ci-triage": ["ci failing", "ci pipeline", "build failing", "github actions failing", "red build", "ci is red", "ci is failing", "ci failed", "red on main", "build is red", "failing check", "failed run", "workflow failed", "why did ci fail", "tests failing in ci", "red pipeline"],
    "pr-review-triage": ["pull requests", "open prs", "review queue", "pr babysitter", "stale prs"],
    "issue-triage": ["triage issues", "backlog of issues", "label issues"],
    "dependency-triage": ["dependabot", "bump dependencies", "outdated packages", "cve in dependency"],
    "brainstorming": [
        "brainstorm", "brainstorm ideas", "explore options", "design this feature", "before we build",
    ],
    "systematic-debugging": ["debug", "root cause", "narrow down the cause"],
    "writing-plans": ["write a plan", "implementation plan", "spec into a plan"],
    "executing-plans": ["execute the plan", "work through the plan"],
    "taste-skill": [
        "landing page", "does not look ai", "doesn't look ai generated", "make it look good",
        "design taste", "beautiful ui", "marketing site", "premium landing", "design dials",
        "portfolio site", "avoid inter",
    ],
    "marketing-plan": [
        "gtm plan", "growth plan", "marketing roadmap", "aarrr", "12 month plan", "90 day plan",
    ],
    "seo-audit": [
        "seo audit", "audit our seo", "technical seo", "crawl issues", "improve our seo",
        "help with seo", "not ranking", "traffic dropped",
    ],
    "ab-testing": ["ab test", "a/b test", "split test", "which version wins", "statistical significance"],
    "analytics": ["event tracking", "ga4", "google analytics", "tracking plan", "utm", "gtm container"],
    "cold-email": ["cold email", "outreach email", "outbound email", "email prospects"],
    "onboarding": ["user onboarding", "activation rate", "first run experience", "onboarding conversion"],
    "pricing": [
        "pricing tiers", "how much should i charge", "packaging", "freemium", "usage-based pricing",
        "usage based pricing", "ai pricing", "credits pricing",
    ],
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
    "odl-pdf": [
        "opendataloader-pdf", "open data loader pdf", "odl pdf",
        "structured pdf extraction", "pdf to json", "pdf bounding boxes",
        "ocr scanned pdf", "scanned pdf ocr", "pdf rag citations",
        "extract tables from pdf", "citation-ready pdf rag",
    ],
    "bosskuai-financial-modeling": [
        "runway", "burn rate", "forecast", "projections", "arr", "mrr", "cash flow",
        "financial model", "revenue model",
    ],
    "dcg": [
        "rm -rf", "destructive command", "dangerous command", "shell safety",
        "guard rails for shell", "block dangerous", "force push protection",
    ],
    "bosskuai-investor-prep": [
        "investor update", "pitch deck", "fundraising", "due diligence", "data room",
        "investor pipeline", "investor outreach", "safe note", "post-money safe", "term sheet",
        "cap table", "dilution", "priced round", "valuation cap", "seed round",
    ],
    "using-git-worktrees": ["worktree", "git worktree", "parallel branches"],
    "bosskuai-mongodb": ["mongodb", "mongo", "aggregation pipeline", "document store"],
    "bosskuai-nuxt-development": [
        "nuxt", "nitro", "nuxt 3", "nuxt 4", "usefetch", "useasyncdata", "nuxt.config", "routerules",
        "server routes",
    ],
    "bosskuai-browser-automation": [
        "headless browser", "puppeteer", "smoke test", "visual regression", "scrape", "qa report",
        "console errors",
    ],
    "bosskuai-malaysia-pdpa-privacy": ["pdpa", "personal data protection", "malaysia privacy"],
    "bosskuai-legal-compliance": ["gdpr", "terms of service", "privacy policy", "compliance"],
    "draft-release-notes": ["release notes", "changelog for release", "what shipped"],
    "bosskuai-content-calendar": ["content calendar", "posting schedule", "editorial calendar"],

    # --- security, tenancy, billing ---
    "bosskuai-prompt-injection-defense": ["prompt injection", "jailbreak", "tool abuse", "memory poisoning", "untrusted input to llm", "untrusted input", "ignore previous instructions", "exfiltration", "agent security", "malicious instructions in a file", "instructions inside fetched content"],
    "bosskuai-tenant-isolation-security": ["multi-tenant", "multitenant", "tenant isolation", "cross-tenant", "row level security", "cross tenant", "tenant leak", "wrong tenant", "org scoping", "sees another customer's data", "workspace isolation"],
    "bosskuai-saas-billing-ops": [
        "subscription", "dunning", "failed payment", "past due", "invoice", "proration",
        "entitlement", "plan change", "refund", "stripe webhook", "billing",
    ],
    "bosskuai-cybersecurity-risk": [
        "security review", "threat model", "vulnerability", "auth bypass", "abuse case",
        "trust boundary", "is this secure", "pentest", "sql injection", "xss", "owasp", "ssrf", "idor",
        "security audit", "secure my", "api security", "secure this",
    ],

    # --- reliability, quality, delivery ---
    "bosskuai-observability-sre": ["logging", "metrics", "tracing", "alerting", "slo", "dashboards", "monitoring", "sli", "dashboard", "health check", "would we even notice", "slos", "alerts", "set up alerts", "error budget", "uptime monitoring", "health checks"],
    "bosskuai-qa-automation-strategy": [
        "test strategy", "what should we test", "regression suite", "ci gate",
        "flaky tests", "test pyramid", "coverage",
    ],
    "bosskuai-integration-testing": [
        "integration test", "contract test", "pact", "test doubles", "fixtures", "mocking strategy",
    ],
    "bosskuai-engineering-delivery": [
        "ship this", "implement this", "build this feature", "delivery plan", "ready to hand off",
    ],
    "bosskuai-coding-best-practices": [
        "code quality", "clean this up", "is this good code", "naming", "maintainability",
    ],
    "bosskuai-devops-iac": [
        "infrastructure as code", "iac", "terraform", "opentofu", "pulumi", "canary deploy",
        "blue green", "environment promotion", "rollback", "drift", "secrets management",
    ],
    "bosskuai-github-workflow": ["pull request workflow", "dependabot", "repo settings"],

    # --- architecture and data ---
    "bosskuai-software-architecture": [
        "architecture", "module boundaries", "system design", "should we split",
        "layering", "structural refactor", "monolith or services",
    ],
    "bosskuai-architecture-deepening": [
        "refactor opportunity", "too coupled", "hard to test", "consolidate modules",
    ],
    "bosskuai-data-architecture": [
        "data model", "warehouse", "analytics pipeline", "entity ownership", "retention policy",
    ],
    "bosskuai-business-logic-review": [
        "state machine", "workflow rules", "approval flow", "edge cases", "business rules",
    ],

    # --- agents and AI systems ---
    "bosskuai-agent-architecture-audit": [
        "agent audit", "agent is broken", "wrapper regression", "agent quality", "why is my agent",
    ],
    "bosskuai-agent-introspection": [
        "agent looping", "burning tokens", "agent drifted", "empty result", "agent stuck",
    ],
    "bosskuai-eval-driven-agent-improvement": [
        "eval", "evals", "eval set", "llm judge", "pass rate", "regression harness", "scorecard",
    ],
    "bosskuai-prompt-optimizer": [
        "improve this prompt", "prompt engineering", "rewrite the prompt", "system prompt",
    ],
    "bosskuai-subagent-delegation": [
        "delegate", "subagent", "parallelize this", "spawn agents", "fan out",
    ],
    "bosskuai-context-budget": ["context window", "too much context", "token bloat", "trim context"],
    "bosskuai-token-saver": [
        "save tokens", "reduce tokens", "cheaper session", "shorter answers", "too much text",
        "get to the point", "no preamble", "terse",
    ],
    "bosskuai-cross-model-escalation": ["stuck", "escalate to another model", "try a different model"],
    "bosskuai-permanent-memory-orchestration": ["remember this", "bossku remember", "save a decision", "record a decision", "record this decision", "project memory", "durable memory", "memory design", "obsidian", "sync memory to obsidian", "export memory to obsidian", "obsidian export", "bossku sync"],

    # --- research, product, growth ---
    "bosskuai-deep-research": [
        "research", "due diligence", "evaluate options", "compare tools", "with citations", "investigate",
    ],
    "bosskuai-market-analysis": ["market size", "tam", "demand validation", "market research"],
    "bosskuai-competitor-intelligence": [
        "competitor monitoring", "track competitors", "competitor pricing changes",
        "what are they shipping",
    ],
    "bosskuai-customer-discovery": [
        "user interviews", "customer interviews", "interview script", "discovery interviews",
    ],
    "bosskuai-product-strategy": ["roadmap", "what should we build", "prioritize", "product direction"],
    "bosskuai-planning-execution": ["milestones", "sequencing", "delivery plan", "who owns what"],
    "bosskuai-growth-experiment": ["growth experiment", "test this channel", "activation experiment"],
    "bosskuai-marketing-growth": [
        "go to market", "gtm", "marketing strategy", "growth strategy", "distribution", "growth loops",
    ],
    "bosskuai-paid-acquisition-monetization": [
        "cac", "ltv to cac", "payback period", "paid acquisition", "monetization model",
    ],
    "bosskuai-sales-strategy": [
        "icp", "pipeline", "objection handling", "founder led sales", "pipeline review", "deal review",
        "sales forecast", "meddic", "deal qualification", "follow up after demo", "follow-up cadence",
        "after a demo",
    ],
    "bosskuai-lead-intelligence": [
        "investor list", "warm intro", "press list", "partner list", "research this person",
        "before my meeting", "email deliverability", "cold email deliverability", "spf", "dkim",
        "dmarc", "spam rate", "one-click unsubscribe", "can-spam", "bulk sender",
    ],
    "bosskuai-seo-geo": [
        "seo geo readiness", "launch seo check", "ssr seo", "ai crawlers", "robots.txt for ai",
        "faq schema", "rich results", "discoverability",
    ],
    "bosskuai-launch-commercialization": ["launch readiness", "ready to launch", "go live", "commercialize"],
    "bosskuai-customer-success-support": [
        "support sop", "ticket triage", "sla", "time to first value", "health score",
        "customer health", "renewal", "qbr", "account management",
    ],
    "bosskuai-operations": ["vendor management", "process docs", "capacity planning", "sop"],
    "bosskuai-analytics-metrics": [
        "north star metric", "metric definitions", "measurement plan", "kpi", "instrumentation",
        "attribution model", "cohort retention",
    ],

    # --- frontend and UI ---
    "bosskuai-ui-ux-design-to-code": [
        "ui review", "interface critique", "responsive", "design to code", "screenshot to code",
        "screenshot into code", "figma to code", "mockup to code", "implement this design",
        "admin dashboard", "dashboard ui", "admin panel", "settings page", "app screen", "product ui",
    ],
    "bosskuai-design-systems": ["design system", "design tokens", "component library", "DESIGN.md"],
    "bosskuai-taste": ["looks ai generated", "make it not look generic", "design direction", "anti-slop"],
    "bosskuai-3d-web-development": ["three.js", "react three fiber", "webgl", "3d scene", "shader"],
    "bosskuai-lenis-smooth-scroll": ["lenis", "smooth scroll", "scroll sync"],
    "bosskuai-hyperframes": ["hyperframes", "hyperframes composition", "hyperframes render", "html to mp4"],

    # --- stack specific ---
    "bosskuai-laravel-development": [
        "laravel", "eloquent", "artisan", "blade", "service container", "dependency injection",
        "form request", "laravel job", "livewire", "filament", "inertia", "laravel migration",
    ],
    "bosskuai-laravel-tdd": ["laravel test", "pest", "phpunit"],
    "bosskuai-laravel-verification": ["verify laravel", "laravel check", "laravel checks", "before a pr"],
    "bosskuai-polyglot-engineering": ["which language", "language tradeoff", "ecosystem choice"],
    "bosskuai-vps-docker-deployment": [
        "vps", "deploy to server", "ship to production", "provision", "nginx", "self host",
        "harden my server", "server hardening", "harden the server", "ufw", "fail2ban",
        "ssh hardening",
    ],
    "bosskuai-redis-caching-queues": ["redis", "cache", "queue", "worker", "pub sub", "rate limit", "job backlog"],

    # --- meta: the toolkit itself ---
    "bosskuai-skill-creator": ["create a skill", "new skill", "write a skill", "improve a skill", "improve this skill", "enhance a skill", "rewrite a skill", "skill description", "skill frontmatter"],
    "bosskuai-skill-stocktake": ["skill audit", "audit skills", "audit existing skills", "audit my skills", "review my skills", "improve existing skills", "enhance existing skills", "skill overlap", "stale skills", "skill health", "stocktake", "which skills are stale"],
    "bosskuai-customize-bosskuai": ["edit agents.md", "skill-index", "bossku config"],
    "bosskuai-claude-md-management": ["claude.md", "rules file", "agent instructions"],
    "bosskuai-claude-code-setup": ["claude code setup", "mcp servers", "hooks", "slash commands"],
    "bosskuai-rules-distill": ["distill rules", "extract principles", "consolidate guidance"],
    "bosskuai-continuous-learning": ["capture this lesson", "learn from this", "save this learning", "save learning", "lesson learned", "what did we learn", "post task learning"],
    "bosskuai-handoff": ["handoff", "hand off", "hand this off", "pass to another agent", "continue in a new session", "next session", "handoff doc", "write a handoff"],
    "bosskuai-council": ["council", "debate this", "go or no go", "multiple perspectives"],
    "bosskuai-search-first": ["is there a library", "build or buy", "existing package", "reinvent"],
    "bosskuai-documentation-lookup": ["official docs", "context7", "look up the docs"],
    "bosskuai-grill-with-docs": ["grill", "verify against docs", "check my understanding"],
    "bosskuai-ponytail": [
        "simplest thing", "yagni", "minimal code", "over-engineered", "unnecessary abstraction",
        "verbose code",
    ],
    "bosskuai-grounding": [
        "hallucination", "hallucinating", "don't make things up", "making things up",
        "cite the source", "cite sources", "only use the provided documents",
        "only from these documents", "verify every claim", "is this accurate",
        "quote the source", "ground the answer", "fact check",
    ],
    "antislop": [
        "anti slop", "antislop", "remove ai slop", "generic ai output", "delivery gate",
        "quality gate", "ai slop", "slop audit", "audit for slop",
    ],
    "antislop-ui": [
        "generic ai ui", "ai generated ui", "gradient cards", "landing page slop",
        "dashboard slop", "distinctive interface", "template looking ui",
    ],
    "antislop-copywriting": [
        "ai copy", "copy slop", "em dashes", "fake claims", "chatbot filler", "rewrite naturally",
        "marketing copy cleanup", "sound human", "less robotic",
    ],
    "antislop-human": [
        "contrast checker", "check contrast", "text over image", "grey on grey", "focus indicator",
        "color-only feedback",
    ],
    "antislop-layoutmobile": [
        "mobile overflow", "mobile reflow", "responsive layout", "tap targets",
        "small screen", "horizontal scroll", "mobile audit",
    ],
    "antislop-code": [
        "ai comments", "remove ai comments", "comment cleanup", "clean up comments",
        "humanize code comments", "comment slop",
    ],
    "bosskuai-headroom": [
        "headroom", "compress tool output", "context compression", "retrieve original",
        "headroom wrap", "headroom deploy", "headroom savings", "large tool output",
    ],
    "bosskuai-autonomous-loops": ["autonomous loop", "loop architecture", "multi step pipeline"],
    "bosskuai-ratchet-loop": ["ratchet", "incremental tightening", "no backsliding"],
    "bosskuai-pr-check": [
        "check this pr", "is my pr ready", "pr ready to merge", "pr status",
        "unresolved review threads", "failing checks on my pr",
    ],
    "bosskuai-greptile-review-loop": ["greptile"],
    "bosskuai-go-development": [
        "golang", "go service", "go microservice", "goroutine", "goroutines", "channels",
        "context.context", "errgroup", "pgx", "sqlc", "go test", "race detector", "pprof",
        "net/http", "echo framework", "chi router", "gin", "go module", "go.mod",
    ],
    "bosskuai-react-development": ["react", "react 19", "react 18", "jsx", "tsx", "hooks", "useeffect", "usestate", "react component", "next.js", "nextjs", "app router", "react router", "tanstack query", "react query", "zustand", "redux", "react-hook-form", "testing library", "vite react", "runs twice", "renders twice", "re-render", "rerender", "re-renders", "refetch", "infinite render loop", "stale closure", "strict mode double"],
    "bosskuai-expo-react-native": [
        "react native", "expo", "expo router", "eas build", "eas update", "expo go",
        "development build", "config plugin", "reanimated", "flashlist", "mobile app",
        "ios and android app", "push notifications", "deep link", "app.json", "eas.json", "expo sdk",
        "upgrade expo", "expo upgrade", "new architecture",
    ],
    "bosskuai-mobile-app-release": [
        "app store", "play store", "google play", "testflight", "app review", "submit the app",
        "publish the app", "release the app", "launch the app", "launch to mobile", "eas submit",
        "fastlane", "staged rollout", "phased release", "app rejected", "data safety",
        "privacy nutrition", "versioncode", "build number", "ota update",
    ],
    "bosskuai-aws-deployment": [
        "aws", "ecs", "fargate", "lambda", "app runner", "eks", "ec2", "rds", "aurora", "s3",
        "cloudfront", "alb", "vpc", "iam", "secrets manager", "cloudwatch", "terraform aws", "cdk",
        "deploy to aws", "amplify", "sqs", "ses", "ecr", "ap-southeast-5", "ecs express mode",
        "express mode",
    ],
    "bosskuai-hostinger-hosting": [
        "hostinger", "hpanel", "kvm vps", "hostinger vps", "hostinger shared hosting",
        "monarx", "suspended by hostinger", "abuse email", "botnet", "xmrig", "crypto miner",
        "server compromised", "hacked server", "rebuild the server", "litespeed",
    ],
    "bosskuai-ci-cd-pipelines": [
        "ci/cd", "ci cd", "cicd", "github actions", "workflow yaml", ".github/workflows",
        "gitlab ci", "pipeline", "pipeline is slow", "speed up ci", "required checks",
        "branch protection", "merge queue", "oidc", "deploy workflow", "release workflow",
        "actions cache", "matrix build", "flaky ci", "actionlint",
    ],
    "bosskuai-web-performance": [
        "core web vitals", "web vitals", "lcp", "inp", "cls", "lighthouse", "pagespeed", "page speed",
        "bundle size", "code splitting", "hydration", "time to interactive", "first contentful paint",
        "largest contentful paint", "layout shift", "slow page", "page load", "website slow",
        "frontend performance", "crux", "rum", "improve inp", "improve lcp", "improve cls", "lcp is",
        "inp is", "interaction to next paint", "lighthouse score", "site is slow", "page is slow",
        "slow website",
    ],
    "bosskuai-cto-strategy": [
        "cto", "as a cto", "chief technology officer", "tech strategy", "technology strategy",
        "technical roadmap", "build vs buy", "build or buy", "engineering org", "org design",
        "hiring plan", "engineering budget", "technical due diligence", "due diligence",
        "soc 2", "iso 27001", "tech radar", "platform bet", "board deck technical",
        "technical debt strategy", "vendor strategy", "ai strategy",
    ],
    "bosskuai-tech-lead": [
        "tech lead", "as a tech lead", "engineering manager", "team lead", "lead engineer",
        "sprint planning", "break down the epic", "slice the work", "estimate", "estimation",
        "rfc", "adr", "definition of done", "code ownership", "codeowners", "pr standards",
        "review culture", "release management", "branching strategy", "on-call rotation",
        "dora metrics", "cycle time", "tech debt triage", "mentoring", "unblock the team",
        "1:1", "one on one", "retro", "postmortem process",
    ],
    "cofounder": ["cofounder", "co-founder", "what should i do next", "advise me"],

    # --- loop-engineering: vendored verbatim, so routing wording lives here not in the descriptions ---
    "loop-budget": ["token budget", "spend budget", "over budget", "budget check", "run log spend", "loop spend", "cap the spend", "early exit"],
    "loop-constraints": ["loop constraints", "denylist", "no auto push", "allowed paths", "loop guardrails", "constraints file", "binding rules for the loop"],
    "loop-triage": ["triage recent changes", "backlog sweep", "sweep the backlog", "loop triage", "findings report", "what needs attention", "state file", "linear board"],
    "loop-verifier": ["verify the fix", "independent verification", "did the fix work", "reasons to reject", "confirm diff scope", "verify before done", "prove it is fixed"],
    "minimal-fix": ["fix this test", "address review comment", "minimal patch", "smallest fix", "smallest diff", "fix the typo", "one line fix", "fix the failing test"],
    "post-merge-scan": ["post merge cleanup", "post-merge", "recent merges", "stale flags", "broken doc links", "follow-up cleanup", "after the merge", "leftover todos"],
    "changelog-scan": ["changelog scan", "release note content", "what merged since", "gather commits for changelog", "changelog drafter", "release prep"],

    # --- emil-skills additions ---
    "animate-expo": ["animate in expo", "react native animation", "reanimated", "gesture handler", "expo haptics", "sheet animation react native", "stutters on device", "screen transition expo", "press feedback", "haptics"],
    "ask-sonner": [
        "sonner", "sonner toast", "toaster component", "toasts not showing", "toast appears twice",
        "toast behind modal", "toast dark mode", "promise toast", "toast notification",
        "toast notifications", "react toast",
    ],
    "write-swift": ["swift", "swift 6", "swiftui", "swift concurrency", "actor isolation", "data race", "retain cycle", "swift testing", "swift macros", "some vs any", "sendable", "main actor", "xcode"],

    # --- i-have-adhd ---
    "i-have-adhd": [
        "adhd", "adhd mode", "i have adhd", "just tell me what to do", "stop burying the answer",
    ],

    # --- ecc (curated subset) ---
    "mysql-patterns": ["mysql", "mariadb", "innodb", "mysql index", "mariadb schema", "replica lag", "replication lag", "mysql connection pool", "mysql query slow", "utf8mb4", "explain analyze"],
    "database-migrations": ["schema migration", "data migration", "zero downtime migration", "rollback migration", "backfill column", "expand contract", "rename column safely", "prisma migrate", "drizzle migration", "add a column without downtime", "migration plan"],
    "error-handling": ["error handling", "typed errors", "error boundary", "retry logic", "retries", "circuit breaker", "exponential backoff", "user facing error message", "custom exception", "result type", "graceful failure"],
    "mcp-server-patterns": ["mcp server", "build an mcp server", "model context protocol", "mcp tool definition", "mcp resources", "streamable http", "stdio transport", "mcp sdk", "write an mcp server", "expose tools over mcp"],
    "e2e-testing": ["playwright", "e2e test", "end to end test", "page object model", "flaky e2e", "playwright config", "playwright ci", "test artifacts", "trace viewer", "e2e suite"],
    "accessibility": ["accessibility", "a11y", "wcag", "wcag 2.2", "screen reader", "keyboard navigation", "keyboard focus", "focus order", "aria", "contrast ratio", "color contrast", "accessible form", "axe"],
    "architecture-decision-records": ["adr", "architecture decision record", "decision record", "record the architecture decision", "why did we choose", "document this decision", "adr log", "write an adr"],
    "vue-patterns": ["vue", "vue 3", "composition api", "pinia", "vue router", "ref vs reactive", "composable", "vue component", "watcheffect", "defineprops", "vite vue", "script setup"],
    "python-patterns": ["python", "pythonic", "pep 8", "type hints", "dataclass", "pydantic", "python idioms", "python code review", "mypy", "asyncio", "python packaging", "python script"],
    "python-testing": ["pytest", "python tests", "pytest fixture", "parametrize", "monkeypatch", "unittest mock", "pytest coverage", "conftest", "test this python"],
    # --- 2026-09-26 skill review: routing for requests that had no owner ---
    "sales-enablement": ["discovery call", "discovery call script", "sales call script", "sales deck"],
    "prospecting": ["lead list", "qualify leads", "prospect list"],
    "cloud": ["browser use cloud", "browser-use-sdk", "browser use sdk", "browser use api key"],
    "open-source": [
        "browser_use library", "browser-use library", "browser use library", "browser use agent",
    ],
    "ai-seo": ["answer engine", "ai overviews", "llms.txt", "cited by chatgpt", "cited by perplexity"],
    "ads": ["google ads", "meta ads", "facebook ads", "linkedin ads", "ppc", "performance max"],
    "launch": ["product hunt", "launch plan", "launch day", "feature announcement", "beta launch"],
    "copywriting": ["landing page copy", "homepage copy", "hero copy", "write copy", "value proposition"],
    "competitor-profiling": ["competitive analysis", "competitor analysis", "competitor profile"],
    "receiving-code-review": [
        "address feedback", "address the review comments", "review comments", "pr comments",
        "unresolved comments", "reviewer feedback", "respond to review",
    ],
    "brandkit": [
        "brand kit", "brand guidelines", "logo system", "logo concept", "identity board",
        "visual identity",
    ],
    "output-skill": [
        "full file", "complete file", "without truncating", "don't truncate", "no placeholders",
    ],
    # --- 2026-09-26 skill review: routing for requests that had no owner ---
    "content-strategy": ["blog posts", "blog post ideas", "content ideas"],
    "cro": ["isn't converting", "not converting", "conversion rate", "landing page conversion"],
}

# High-confidence task boundaries. These live in the generated index so agents can
# avoid a superficially related skill without adding text to every loaded prompt.
CURATED_EXCLUSIONS: dict[str, list[str]] = {
    "odl-pdf": [
        "merge", "split", "rotate", "form filling", "fill a pdf form",
        "office conversion", "word to pdf", "docx to pdf", "docx or pdf",
        "xlsx or pdf", "pptx or pdf", "pdf/ua tagging",
    ],
    # --- 2026-09-26 skill review: routing for requests that had no owner ---
    "animate": ["react native", "expo", "reanimated"],
    "animate-expo": ["hover animation", "hover effect", "website", "web page", "next.js"],
    "scroll-world": ["three.js", "react three fiber", "r3f", "webgl", "gsap", "scrolltrigger", "lenis"],
    "cloud": [
        "deploy my app", "to the cloud", "cloud bill", "cloud cost", "cloud provider", "aws", "gcp",
        "azure",
    ],
    "open-source": [
        "open source license", "license", "make this project open source", "open-source this",
        "contributing guide",
    ],
    "bosskuai-prompt-injection-defense": [
        "sql injection", "nosql injection", "command injection", "dependency injection",
    ],
    "schema": [
        "database schema", "db schema", "schema migration", "sql schema", "prisma schema",
        "graphql schema", "table schema", "schema design", "json schema", "postgres", "mysql",
    ],
    "bosskuai-taste": [
        "admin dashboard", "admin panel", "this dashboard", "build a dashboard", "dashboard ui",
        "data table",
    ],
    "taste-skill": [
        "admin dashboard", "admin panel", "this dashboard", "build a dashboard", "dashboard ui",
        "data table",
    ],
    # --- 2026-09-26 skill review: routing for requests that had no owner ---
    "pricing": ["a/b test", "ab test", "split test"],
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
    "bosskuai-go-development": "coder",
    "bosskuai-react-development": "coder",
    "bosskuai-expo-react-native": "coder",
    "bosskuai-mobile-app-release": "planner",
    "bosskuai-aws-deployment": "coder",
    "bosskuai-hostinger-hosting": "coder",
    "bosskuai-ci-cd-pipelines": "coder",
    "bosskuai-web-performance": "coder",
    "bosskuai-cto-strategy": "planner",
    "bosskuai-tech-lead": "planner",
    "graft": "coder",
    "animate-expo": "coder",
    "ask-sonner": "coder",
    "write-swift": "coder",
    "mysql-patterns": "coder",
    "database-migrations": "coder",
    "error-handling": "coder",
    "mcp-server-patterns": "coder",
    "e2e-testing": "coder",
    "accessibility": "reviewer",
    "architecture-decision-records": "planner",
    "vue-patterns": "coder",
    "python-patterns": "coder",
    "python-testing": "coder",
    "antislop": "reviewer",
    "antislop-ui": "reviewer",
    "antislop-copywriting": "reviewer",
    "antislop-human": "reviewer",
    "antislop-layoutmobile": "reviewer",
    "antislop-code": "reviewer",
    "bosskuai-headroom": "coder",
    "odl-pdf": "coder",
    # --- 2026-09-26 skill review: routing for requests that had no owner ---
    "bosskuai-hyperframes": "coder",
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
# Quote marks only count at word edges, so the apostrophe in "isn't" neither opens
# nor closes a span and "analytics isn't working" survives whole.
_QUOTED = re.compile(r"(?<!\w)['‘\"“]((?:[^'‘’\"“”]|(?<=\w)['’](?=\w)){3,60}?)['’\"”](?!\w)")
_TRIGGER_CLAUSE = re.compile(r"\b(?:Triggers?|Use (?:this )?when(?:ever)?)\b\s*:?\s*([^.]{3,200})", re.I)
# `/` separates alternatives ("Three.js/React"), except inside initialisms like "A/B".
_CLAUSE_SPLIT = re.compile(r"[,;()]| or | and |(?<!\b\w)/(?!\w\b)")
_STRAY_QUOTE = re.compile(r"(?<!\w)['‘’\"“”]|['‘’\"“”](?!\w)")
# Third-person scaffolding never occurs in a first-person request, so strip it:
# "the user wants to create a community strategy" -> "create a community strategy".
_SCAFFOLD = re.compile(
    r"^(?:(?:the user|someone|they)\s+(?:\w+ly\s+)?(?:asks?|wants?|needs?|mentions?|says?|shares?|is|has|already has)"
    r"|(?:wants?|needs?|asks?))\b(?:\s+(?:to|for|about|an?)\b)?\s*"
)


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
        # Scoring matches against a lowercased query, so a trigger written with a
        # capital ("what should I use for toasts") could never earn its phrase bonus.
        p = re.sub(r"\s+", " ", p).strip().lower()
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

    # Quoted examples are already captured; blank them so the split below cannot
    # shred `"A/B test,"` into `the user mentions "a` + `b test`. Keep a period that
    # sat inside the quotes, or the clause runs on into the next sentence.
    unquoted = _QUOTED.sub(lambda q: " . " if q.group(1).rstrip().endswith(".") else " , ", description)
    for m in _TRIGGER_CLAUSE.finditer(unquoted):
        parts = _CLAUSE_SPLIT.split(m.group(1))
        if len(m.group(1)) == 200:
            parts = parts[:-1]  # the cap cut the last part mid-word
        for part in parts:
            phrase = _SCAFFOLD.sub("", part.strip().lower())
            # Unlike author-quoted examples, clause fragments need two content words,
            # or "improve it" would claim every request that says "improve it".
            if 4 <= len(phrase) <= 50 and 2 <= len(tokenize(phrase)) <= 5:
                derived.append(phrase)

    # The scorer only credits multi-word phrases; anything else is dead weight.
    curated = set(triggers)
    useful = [
        p for p in derived
        if p not in curated and len(p.split()) >= 2 and not _STRAY_QUOTE.search(p)
    ]
    return _clean(triggers, 40), _clean(useful, 40)


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
            "exclusions": _clean(CURATED_EXCLUSIONS.get(sid, []), 20),
            "phrases": phrases,
            "keywords": kw[:60],
            "model_role": _derive_role(sid, description),
            "pack": vendored.get(sid, "bossku"),
        }
        # Claude Code refuses model calls to these; the user runs them as /<id>.
        if str(_parse_frontmatter(text).get("disable-model-invocation", "")).lower() == "true":
            entries[sid]["user_invoked"] = True

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
