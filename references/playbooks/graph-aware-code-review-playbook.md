# Graph-Aware Code Review Playbook

Use this when `code-review-graph` MCP tools are installed and the task is a PR review, pre-merge check, risky refactor review, or "what is impacted by this change?" investigation.

## Goal

Review the smallest relevant set of code while still checking real evidence. The graph should prioritize files, callers, tests, and flows; it must not replace direct source reads for findings.

## Workflow

1. Establish the diff base: use the provided PR base, `origin/main`, `main`, or `HEAD~1` for local delta reviews.
2. Call `get_minimal_context_tool(task="review changes", base=<base>)` first.
3. If graph stats show no build or stale state, call `build_or_update_graph_tool(base=<base>, postprocess="minimal")`.
4. Call `detect_changes_tool(base=<base>, detail_level="minimal")` for a risk-scored review map.
5. Escalate only where needed:
   - `detect_changes_tool(..., detail_level="standard")` for unclear risk or missing detail.
   - `get_review_context_tool(base=<base>, max_depth=2, include_source=True)` for focused snippets.
   - `get_affected_flows_tool(base=<base>)` when runtime paths matter.
   - `query_graph_tool(pattern="callers_of" | "tests_for" | "importers_of", target=<symbol>)` for public functions, API surfaces, and shared utilities.
6. Read the actual diff, high-risk changed files, relevant callers, and tests before writing findings.
7. Report blast radius, test gaps, and residual graph limits separately from confirmed code defects.

## Fallback

If the graph tools are missing, stale beyond quick repair, or do not cover the language/framework area, use the normal review flow: `git diff`, `rg` call-site searches, targeted file reads, test inspection, and build/test verification.

## Guardrails

- Do not paste large graph outputs into the review. Summarize risk, impacted files, flows, and test gaps.
- Do not block a PR from graph inference alone. Blocking findings need file:line evidence in source or tests.
- Prefer `detail_level="minimal"` first. Use standard detail only for high-risk or ambiguous areas.
- Watch for false positives in conservative blast radius results; extra impacted files are a prompt to inspect, not a finding by themselves.
