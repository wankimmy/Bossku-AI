# BosskuAI

**BosskuAI is an open-source AI agent orchestration layer for software development.** It adds planning, automated code review, approval gates, and persistent memory on top of Cursor, Claude Code, Codex, and OpenCode — so AI coding assistants plan before they edit, audit their own diffs, and remember what they learn.

Instead of one-shot file edits, every task runs through a multi-agent pipeline: **plan → execute → audit → approve**. You get a run dashboard, 100+ skills, and optional local inference with Ollama.

> Free and open source (MIT). Runs on your machine. No account required. No cloud lock-in.

**Looking for:** AI coding assistant guardrails · multi-agent orchestration for Cursor or Claude Code · local-first LLM dev tools · plan-and-review before merge · open-source alternative to LangGraph/CrewAI for coding workflows.

---

## See BosskuAI

[![Watch the BosskuAI product tour](docs/assets/bosskuai-product-tour-preview.gif)](docs/assets/bosskuai-product-tour.mp4)

Watch the full product tour: type a task, follow the agents in Pixel Office, inspect each run workspace, approve risky work, review the audit, and keep the context for the next run.

## What BosskuAI does

1. **Plans before editing** — you see the plan before any file changes.
2. **Audits its own work** — a second agent reviews the diff for bugs, security, and missing tests.
3. **Pauses on risky work** — auth, payments, and migrations need your approval.
4. **Remembers across sessions** — durable memory so the next run does not start from zero.

---

## How it compares

| Feature | BosskuAI | Plain AI (Cursor, Claude Code) | LangChain / CrewAI | LangGraph |
|---|---|---|---|---|
| Plans before editing | Yes | Sometimes | No | No |
| Audits its own work | Yes | No | No | No |
| Approval gates | Yes | No | No | No |
| Persistent memory | Yes | No | No | Optional |
| Works with your existing tool | Yes | — | Build your own | Build your own |
| Local-first (Ollama) | Yes | Partial | No | No |
| Run dashboard | Yes | No | No | No |
| Open source | Yes (MIT) | Varies | Yes | Yes |

**In one sentence:** BosskuAI is open-source AI agent orchestration that adds plan → audit → approve guardrails and persistent memory to Cursor, Claude Code, Codex, and OpenCode — local-first, no vendor lock-in.

---

## How it works

```
Your prompt → Router → Planner → Executor → Auditor → Final Review → Memory
```

Simple questions take a short path. Risky changes get the full pipeline (plan, edit, audit, review, approve).

---

## Get started

| Path | Best for |
|---|---|
| **[Docker web app](docs/get-started.md#path-1--docker-web-app-5-minutes)** | Dashboard, run history, memory, and approval gates |
| **[Repo toolkit](docs/get-started.md#path-2--add-to-an-existing-repo)** | Drop rules and skills into an existing project |

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI && cd bosskuAI
```

Then follow **[docs/get-started.md](docs/get-started.md)** — clone, configure one AI provider, run Docker, open http://localhost:28470, and run your first task in about 5 minutes.

Windows desktop installer and other paths: **[docs/installation.md](docs/installation.md)**.

---

## FAQ

**What is BosskuAI?**  
An open-source orchestration layer that routes AI coding tasks through plan → execute → audit → approve agents, with memory and a dashboard. It works with Cursor, Claude Code, Codex, and OpenCode.

**Is BosskuAI a replacement for Cursor or Claude Code?**  
No. It sits on top of the tool you already use and adds structure, review, and memory.

**Does BosskuAI work with Ollama?**  
Yes. Run models locally for privacy, or use Anthropic, Codex, or other providers.

**Is BosskuAI free?**  
Yes. MIT licensed. You only pay for cloud AI APIs if you choose them.

**How is BosskuAI different from LangGraph or CrewAI?**  
BosskuAI is a ready-made workflow for software teams using AI coding assistants — not a library you assemble yourself. See [`docs/comparison.md`](docs/comparison.md).

More answers: [`docs/faq.md`](docs/faq.md).

---

## Documentation

| Doc | Covers |
|---|---|
| [`docs/get-started.md`](docs/get-started.md) | Full setup, commands, ports, troubleshooting |
| [`docs/quickstart.md`](docs/quickstart.md) | Shortest path to one successful run |
| [`docs/what-is-bossku-ai.md`](docs/what-is-bossku-ai.md) | Plain explanation and boundaries |
| [`docs/architecture.md`](docs/architecture.md) | System map |
| [`docs/skills.md`](docs/skills.md) | Skill system |
| [`docs/faq.md`](docs/faq.md) | Common questions |

Full index: [`docs/README.md`](docs/README.md).

---

## Contributing

Contributions welcome — bug reports, docs fixes, and new skills.

- Open an issue on GitHub for bugs or ideas.
- Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before code changes.
- Security issues: follow [`SECURITY.md`](SECURITY.md) (do not open a public issue).
- AI assistants in this repo: see [`AGENTS.md`](AGENTS.md).

If BosskuAI is useful to you, a star on GitHub helps other people find it.

---

## License

Released under the license in [`LICENSE`](LICENSE).
