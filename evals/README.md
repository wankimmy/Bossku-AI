# BosskuAI Evals

These evals are local health checks for the workspace layer.

They measure:

- prompt-surface size for always-loaded files
- routing-fit on curated task prompts
- retrieval relevance on curated memory queries

They do not measure true model intelligence or guarantee answer quality.

Run:

```bash
python3 ./scripts/eval_workspace.py
```

The retrieval benchmark writes a local SQLite file under `evals/retrieval-fixtures/`. That database is ignored by git.
