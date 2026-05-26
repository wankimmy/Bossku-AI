# BosskuAI Evals

These are local checks for BosskuAI maintainers. They are not product benchmarks and they do not prove model intelligence.

The evals check:

- always-loaded prompt size
- routing fit on sample tasks
- memory retrieval fixtures
- a small workflow proxy comparing baseline behavior with the BosskuAI layer

Run from the repo root:

```bash
python -S scripts/eval_workspace.py
```

The retrieval benchmark may write a local SQLite file under `evals/retrieval-fixtures/`. That generated database is ignored by git.
