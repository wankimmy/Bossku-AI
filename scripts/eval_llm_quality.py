#!/usr/bin/env python3
"""BosskuAI LLM-quality eval.

Unlike the other evals in this repo, this one grades actual model answers
against per-task rubrics. It does NOT call a model itself — it produces the
artifacts a human or a grading model needs:

  Modes:
    --emit-prompts   Write one grading prompt per case under runs/<run-id>/prompts/.
                     Each prompt asks the candidate model to answer the task.
    --emit-rubrics   Write one rubric prompt per case under runs/<run-id>/rubrics/.
                     Each rubric prompt asks a grader to score a saved answer.
    --score          Read graded JSONs from runs/<run-id>/grades/ and produce
                     a summary report. Each grade JSON must follow the schema:
                       {
                         "id": "laravel-double-charge",
                         "criteria": [
                           {"weight": 3, "score": 1.0, "note": "..."},
                           ...
                         ],
                         "must_avoid_violations": [],   # list of strings actually present
                         "answer_excerpt": "..."        # first 200 chars for the report
                       }

  Score per case = sum(weight*score) / sum(weight) where score in [0,1].
  Pass per case  = score >= --pass-threshold (default 0.7).
  Hard fail      = ANY must_avoid string present  =>  case scores 0 regardless.

This separates evaluation from runtime:
  1. Run --emit-prompts. Feed each prompt to the candidate model. Save the
     answer to runs/<run-id>/answers/<id>.md.
  2. Run --emit-rubrics --answers-dir runs/<run-id>/answers. Feed each rubric
     prompt to a grader (a different model, or a human). Save graded JSON to
     runs/<run-id>/grades/<id>.json.
  3. Run --score. Get a single deterministic report.

This is the scaffolding the v1.8.3 review called out as the missing piece for
true quality measurement. It does not magically grade answers — it just makes
the grading reproducible.
"""
from __future__ import annotations
import argparse, json, sys, datetime
from pathlib import Path


def load_cases(root: Path) -> list[dict]:
    return json.loads((root / "evals/llm-quality-cases.json").read_text())


def grading_prompt_for(case: dict) -> str:
    rubric_lines = "\n".join(
        f"  {i+1}. (weight={c['weight']}) {c['criterion']}"
        for i, c in enumerate(case["rubric"])
    )
    must_avoid = "\n".join(f"  - {p}" for p in case.get("must_avoid", []))
    return f"""You are grading a candidate model's answer to a senior-engineer task.

TASK GIVEN TO THE CANDIDATE:
{case['task']}

RUBRIC (score each criterion in [0.0, 1.0]; 1.0 = fully satisfied,
0.5 = partially satisfied, 0.0 = absent or wrong):
{rubric_lines}

HARD FAILS — if the candidate's answer contains any of these as
recommended actions, the case automatically scores 0:
{must_avoid or "  (none specified)"}

INSTRUCTIONS:
1. Read the candidate's answer carefully.
2. Score each rubric item independently. Do not give partial credit unless
   the answer demonstrably addresses the criterion in substance.
3. Note one short observation per criterion (max 200 chars).
4. List any must-avoid phrases you found in the candidate's answer.
5. Return a single JSON object (no prose, no markdown fences) matching:

{{
  "id": "{case['id']}",
  "criteria": [
    {{"weight": <int>, "score": <float>, "note": "<string>"}},
    ...   // one entry per rubric line, IN ORDER
  ],
  "must_avoid_violations": [<string>, ...],
  "answer_excerpt": "<first 200 chars of the candidate answer>"
}}

CANDIDATE ANSWER FOLLOWS BETWEEN THE MARKERS.

===BEGIN ANSWER===
{{ANSWER}}
===END ANSWER===
"""


def task_prompt_for(case: dict) -> str:
    return case["task"].strip() + "\n"


def cmd_emit_prompts(root: Path, run_id: str) -> int:
    cases = load_cases(root)
    base = root / "evals/runs" / run_id / "prompts"
    base.mkdir(parents=True, exist_ok=True)
    for c in cases:
        (base / f"{c['id']}.txt").write_text(task_prompt_for(c))
    manifest = {
        "run_id": run_id,
        "created_at": datetime.datetime.now(datetime.timezone.utc).isoformat() + "Z",
        "cases": [c["id"] for c in cases],
    }
    (root / "evals/runs" / run_id / "manifest.json").write_text(
        json.dumps(manifest, indent=2)
    )
    print(f"Wrote {len(cases)} prompts to evals/runs/{run_id}/prompts/")
    print(f"Next: feed each .txt to the candidate model, save the answer to "
          f"evals/runs/{run_id}/answers/<id>.md")
    return 0


def cmd_emit_rubrics(root: Path, run_id: str, answers_dir: Path | None) -> int:
    cases = load_cases(root)
    base = root / "evals/runs" / run_id
    rubrics_dir = base / "rubrics"
    rubrics_dir.mkdir(parents=True, exist_ok=True)

    answers_root = answers_dir or (base / "answers")
    if not answers_root.exists():
        print(f"WARN: answers dir does not exist: {answers_root}")
        print("      Generating rubric templates with {ANSWER} placeholders.")
    else:
        print(f"Reading candidate answers from {answers_root}")

    written = 0
    for c in cases:
        rubric = grading_prompt_for(c)
        ans_path = answers_root / f"{c['id']}.md"
        if ans_path.exists():
            answer = ans_path.read_text()
            rubric = rubric.replace("{ANSWER}", answer)
        # leave {ANSWER} as a placeholder if not found, so it's still useful
        (rubrics_dir / f"{c['id']}.txt").write_text(rubric)
        written += 1
    print(f"Wrote {written} rubric prompts to {rubrics_dir}")
    print(f"Next: feed each rubric.txt to the grading model, save JSON to "
          f"evals/runs/{run_id}/grades/<id>.json")
    return 0


def score_one(case: dict, grade: dict) -> dict:
    rubric = case["rubric"]
    crit = grade.get("criteria", [])
    if len(crit) != len(rubric):
        return {
            "id": case["id"], "score": 0.0, "pass": False,
            "error": f"criteria count mismatch (expected {len(rubric)}, got {len(crit)})",
        }
    total_w, got_w = 0, 0.0
    for r, c in zip(rubric, crit):
        w = int(r["weight"])
        s = float(c.get("score", 0))
        s = max(0.0, min(1.0, s))
        total_w += w
        got_w += w * s

    score = got_w / total_w if total_w else 0
    violations = grade.get("must_avoid_violations", []) or []
    if violations:
        score = 0.0
    return {
        "id": case["id"],
        "skill": case["skill"],
        "score": round(score, 3),
        "violations": violations,
        "rubric_total": total_w,
        "rubric_got": round(got_w, 2),
    }


def cmd_score(root: Path, run_id: str, threshold: float, json_out: bool) -> int:
    cases = load_cases(root)
    grades_dir = root / "evals/runs" / run_id / "grades"
    if not grades_dir.exists():
        print(f"ERROR: grades dir does not exist: {grades_dir}")
        return 2

    by_id = {c["id"]: c for c in cases}
    rows, missing = [], []
    for c in cases:
        gpath = grades_dir / f"{c['id']}.json"
        if not gpath.exists():
            missing.append(c["id"])
            continue
        try:
            grade = json.loads(gpath.read_text())
        except json.JSONDecodeError as e:
            print(f"ERROR: bad JSON in {gpath}: {e}")
            return 2
        rows.append(score_one(by_id[c["id"]], grade))

    passed = sum(1 for r in rows if r["score"] >= threshold and "error" not in r)
    total_graded = len(rows)
    avg = sum(r["score"] for r in rows) / total_graded if total_graded else 0

    report = {
        "run_id": run_id,
        "threshold": threshold,
        "graded": total_graded,
        "missing": missing,
        "passed": passed,
        "pass_rate": round(passed / total_graded, 3) if total_graded else 0,
        "avg_score": round(avg, 3),
        "results": rows,
    }

    if json_out:
        print(json.dumps(report, indent=2))
        return 0 if passed == total_graded and not missing else 1

    print(f"BosskuAI LLM-quality eval — run {run_id}")
    print(f"Threshold:  {threshold:.2f}")
    print(f"Graded:     {total_graded}/{len(cases)}")
    if missing:
        print(f"MISSING:    {missing}")
    print(f"Passed:     {passed}/{total_graded}  (pass rate {report['pass_rate']:.0%})")
    print(f"Avg score:  {avg:.3f}\n")
    for r in rows:
        if "error" in r:
            print(f"  ERR  {r['id']}  -  {r['error']}")
            continue
        marker = "PASS" if r["score"] >= threshold else "FAIL"
        viol = f"   VIOLATIONS={r['violations']}" if r["violations"] else ""
        print(f"  {marker} {r['id']:<28}  score={r['score']:.2f}  ({r['rubric_got']}/{r['rubric_total']}){viol}")
    return 0 if passed == total_graded and not missing else 1


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(Path(__file__).resolve().parents[1]))
    ap.add_argument("--run-id", default=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
    ap.add_argument("--threshold", type=float, default=0.7)
    ap.add_argument("--json", action="store_true")
    ap.add_argument("--answers-dir", help="path to candidate answers (default: evals/runs/<run-id>/answers)")
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--emit-prompts", action="store_true",
                   help="Write task prompts for the candidate model.")
    g.add_argument("--emit-rubrics", action="store_true",
                   help="Write grading rubric prompts (with answers inlined if present).")
    g.add_argument("--score", action="store_true",
                   help="Score saved grade JSONs.")
    args = ap.parse_args()

    root = Path(args.root).resolve()

    if args.emit_prompts:
        return cmd_emit_prompts(root, args.run_id)
    if args.emit_rubrics:
        return cmd_emit_rubrics(
            root, args.run_id,
            Path(args.answers_dir).resolve() if args.answers_dir else None
        )
    if args.score:
        return cmd_score(root, args.run_id, args.threshold, args.json)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
