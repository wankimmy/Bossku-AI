---
name: markitdown
description: Convert Office documents, PDFs, HTML, and other files to Markdown for agent-readable context. Use when the user needs docx/xlsx/pptx/pdf/html converted, or when grep/read tools cannot parse a binary document.
---

# MarkItDown

Thin Bossku skill for the [Microsoft MarkItDown](https://github.com/microsoft/markitdown) CLI. The library is **not** bundled in BosskuAI.

## When to use

- User uploads or references `.docx`, `.xlsx`, `.pptx`, `.pdf`, `.html`, or similar
- You need Markdown from a URL or file path before summarizing or editing
- Repo docs live in Office formats and must become agent-readable text

## Install (user machine)

```bash
pip install "markitdown[all]"
```

Optional extras for specific formats are documented upstream.

## CLI examples

```bash
markitdown path/to/document.pdf > document.md
markitdown path/to/deck.pptx -o deck.md
markitdown https://example.com/page.html -o page.md
```

## Guardrails

- Do not commit converted files that contain secrets or PII unless the user approves.
- Prefer converting only the files needed for the task; avoid bulk-exporting entire drives.
- If conversion fails, report the format and suggest installing the matching optional dependency from upstream docs.

## License

Microsoft MarkItDown — MIT. See upstream `LICENSE`.
