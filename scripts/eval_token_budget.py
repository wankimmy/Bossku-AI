#!/usr/bin/env python3
"""BosskuAI token-budget eval.

Measures the actual cost of single-call vs deep-mode flows on the same task,
using real token counts from model runs. Like eval_llm_quality.py, this
harness does NOT run models itself — it produces the prompts and reads the
recorded results.

Pipeline:

  1. --emit-prompts      writes one task prompt per case under
                         evals/runs/<run-id>/prompts/<case-id>.txt
                         User feeds each to Claude Code in BOTH modes
                         (single-call AND the case's deep_mode_command),
                         records token usage to JSONs under
                         evals/runs/<run-id>/usage/<case-id>-<mode>.json

  2. --score             reads the usage JSONs, compares modes per case,
                         produces a deterministic cost report.

Usage JSON schema (one file per case per mode):

  {
    "case_id": "cofounder-decision",
    "mode": "single_call" | "deep_mode",
    "deep_mode_command": "/decide",          // for deep_mode runs only
    "calls": [
      {
        "role": "primary" | "critic" | "specialist:laravel" | ...,
        "input_tokens": 5234,
        "cached_input_tokens": 4100,
        "output_tokens": 612,
        "wall_time_ms": 8400
      }
      // ...
    ]
  }

The schema matches the `usage` field Anthropic returns in API responses
(input_tokens, cache_read_input_tokens, output_tokens). Claude Code shows
this per call; copy the numbers in.

Effective cost is computed as:
  effective = uncached_input * 1.0
            + cached_input   * 0.1     (Anthropic cache discount ~90%)
            + output_tokens  * (output_price / input_price ratio, default 5x for Sonnet)

The default ratio is configurable via --output-input-ratio.
"""
from __future__ import annotations
import argparse, datetime, json, sys
from pathlib import Path


def load_cases(root: Path) -> list[dict]:
    return json.loads((root / "evals/token-budget-cases.json").read_text())


def cmd_emit_prompts(root: Path, run_id: str) -> int:
    cases = load_cases(root)
    base = root / "evals/runs" / run_id
    (base / "prompts").mkdir(parents=True, exist_ok=True)
    (base / "usage").mkdir(parents=True, exist_ok=True)
    for c in cases:
        prompt = (
            f"Task ({c['category']}, case={c['id']}):\n\n"
            f"{c['task'].strip()}\n\n"
            f"--- Run this twice ---\n"
            f"Mode A (single_call): no slash command. Load only `{c['single_call_skill']}` and answer.\n"
            f"Mode B (deep_mode):   use `{c['deep_mode_command']}` slash command.\n\n"
            f"After each run, record the token usage to:\n"
            f"  evals/runs/{run_id}/usage/{c['id']}-single_call.json\n"
            f"  evals/runs/{run_id}/usage/{c['id']}-deep_mode.json\n"
            f"Schema in scripts/eval_token_budget.py docstring.\n"
        )
        (base / "prompts" / f"{c['id']}.txt").write_text(prompt)
    manifest = {
        "run_id": run_id,
        "created_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
        "cases": [c["id"] for c in cases],
    }
    (base / "manifest.json").write_text(json.dumps(manifest, indent=2))
    print(f"Wrote {len(cases)} task prompts to evals/runs/{run_id}/prompts/")
    print(f"After running each in both modes, record usage to evals/runs/{run_id}/usage/")
    return 0


def effective_cost(call: dict, output_input_ratio: float) -> float:
    """Convert a single call's token usage into 'effective input tokens equivalent'."""
    in_t = float(call.get("input_tokens", 0) or 0)
    cached = float(call.get("cached_input_tokens", 0) or 0)
    out_t = float(call.get("output_tokens", 0) or 0)
    uncached = max(0.0, in_t - cached)
    return uncached * 1.0 + cached * 0.1 + out_t * output_input_ratio


def summarize(usage_entry: dict, output_input_ratio: float) -> dict:
    calls = usage_entry.get("calls", [])
    total_in = sum(c.get("input_tokens", 0) or 0 for c in calls)
    total_cached = sum(c.get("cached_input_tokens", 0) or 0 for c in calls)
    total_out = sum(c.get("output_tokens", 0) or 0 for c in calls)
    total_wall = sum(c.get("wall_time_ms", 0) or 0 for c in calls)
    eff = sum(effective_cost(c, output_input_ratio) for c in calls)
    cache_hit_rate = (total_cached / total_in) if total_in else 0
    return {
        "calls": len(calls),
        "input_tokens": total_in,
        "cached_input_tokens": total_cached,
        "output_tokens": total_out,
        "wall_time_ms": total_wall,
        "cache_hit_rate": round(cache_hit_rate, 3),
        "effective_cost": round(eff, 1),
    }


def cmd_score(root: Path, run_id: str, output_input_ratio: float, json_out: bool) -> int:
    cases = load_cases(root)
    usage_dir = root / "evals/runs" / run_id / "usage"
    if not usage_dir.exists():
        print(f"ERROR: usage dir does not exist: {usage_dir}")
        return 2

    rows, missing = [], []
    for c in cases:
        sc_path = usage_dir / f"{c['id']}-single_call.json"
        dm_path = usage_dir / f"{c['id']}-deep_mode.json"
        if not sc_path.exists():
            missing.append(f"{c['id']}-single_call.json"); continue
        if not dm_path.exists():
            missing.append(f"{c['id']}-deep_mode.json"); continue

        sc = summarize(json.loads(sc_path.read_text()), output_input_ratio)
        dm = summarize(json.loads(dm_path.read_text()), output_input_ratio)
        ratio = (dm["effective_cost"] / sc["effective_cost"]) if sc["effective_cost"] else float("inf")
        rows.append({
            "case": c["id"],
            "command": c["deep_mode_command"],
            "single_call": sc,
            "deep_mode": dm,
            "deep_to_single_ratio": round(ratio, 2),
            "wall_time_ratio": round(
                (dm["wall_time_ms"] / sc["wall_time_ms"]) if sc["wall_time_ms"] else float("inf"), 2
            ),
        })

    report = {
        "run_id": run_id,
        "output_input_ratio": output_input_ratio,
        "missing": missing,
        "results": rows,
    }

    if json_out:
        print(json.dumps(report, indent=2))
        return 0 if not missing else 1

    print(f"BosskuAI token-budget eval — run {run_id}")
    print(f"Output/input price ratio: {output_input_ratio} (Sonnet default ~5x)")
    if missing:
        print(f"MISSING usage files: {missing}\n")
    print(f"{'case':<28} {'cmd':<11} {'single':>9} {'deep':>9} {'ratio':>6} {'wall':>6}")
    print(f"{'-'*28} {'-'*11} {'-'*9} {'-'*9} {'-'*6} {'-'*6}")
    for r in rows:
        sc = r["single_call"]; dm = r["deep_mode"]
        print(f"{r['case']:<28} {r['command']:<11} "
              f"{sc['effective_cost']:>9.0f} {dm['effective_cost']:>9.0f} "
              f"{r['deep_to_single_ratio']:>5.2f}x {r['wall_time_ratio']:>5.2f}x")

    if rows:
        avg_ratio = sum(r["deep_to_single_ratio"] for r in rows) / len(rows)
        print(f"\nAverage deep-mode/single-call cost ratio: {avg_ratio:.2f}x")
        print()
        print("Reading the result:")
        print("  ratio < 1.0  : deep-mode is cheaper than single-call (caching working hard)")
        print("  ratio 1.0-1.5: deep-mode is comparable; accuracy must justify the small premium")
        print("  ratio 1.5-2.5: typical for deep-mode with partial caching")
        print("  ratio > 3.0  : caching not hitting; check session continuity and prefix stability")
        print()
        print("Cache hit rates per case:")
        for r in rows:
            print(f"  {r['case']:<28} single_call={r['single_call']['cache_hit_rate']:.0%} "
                  f"deep_mode={r['deep_mode']['cache_hit_rate']:.0%}")

    return 0 if not missing else 1


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    ap.add_argument("--run-id", default=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
    ap.add_argument("--output-input-ratio", type=float, default=5.0,
                    help="Ratio of output token price to input token price. Sonnet ~5x.")
    ap.add_argument("--json", action="store_true")
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--emit-prompts", action="store_true")
    g.add_argument("--score", action="store_true")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    if args.emit_prompts:
        return cmd_emit_prompts(root, args.run_id)
    if args.score:
        return cmd_score(root, args.run_id, args.output_input_ratio, args.json)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
