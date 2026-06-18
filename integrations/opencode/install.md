# Install BosskuAI in OpenCode

BosskuAI ships an OpenCode harness through the standard repo toolkit installer. It creates:

- `.opencode/opencode.jsonc` with BosskuAI references.
- `.opencode/agent/*.md` for orchestrator, planner, executor, auditor, final reviewer, and helper subagents.
- `.opencode/command/*.md` for `/bossku`, `/plan`, `/verify`, `/route`, `/quality-gate`, `/skill-stocktake`, and `/continuous-learning`.

## Install

From the BosskuAI repo:

```bash
./scripts/install.sh /path/to/your/project --profile core
```

Windows PowerShell:

```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

Use `--profile full` / `-Profile full` when you want every BosskuAI skill and command.

## Verify

```bash
bash scripts/check-workspace.sh /path/to/your/project --profile core
```

Then open the target project in OpenCode and try:

```text
/bossku confirm which BosskuAI files are available in this repo
/plan add a tiny safe change without editing yet
/verify inspect the current diff and run relevant checks
```

## Manual fallback

If your OpenCode setup cannot load `.opencode` files, copy the bullets from [`rules.md`](rules.md) into your OpenCode system rules. The generated `.opencode` harness is preferred because it keeps agents and commands versioned with the repo.
